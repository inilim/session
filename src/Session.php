<?php

namespace Inilim\Session;

final class Session
{
    protected const ROOT_NAME_SEGMENT = '_main';

    protected string $segmentName = self::ROOT_NAME_SEGMENT;
    protected bool $init          = false;
    protected bool $changed       = false;
    protected bool $autoCommit    = false;
    protected ?string $name       = null;
    protected ?string $id         = null;
    /**
     * @var mixed[][]
     */
    protected array $data         = [];

    /**
     * @return self
     */
    function segment(string $name)
    {
        if ($name === self::ROOT_NAME_SEGMENT) return $this;
        $new               = new self;
        $new->data         = &$this->data;
        $new->init         = &$this->init;
        $new->changed      = &$this->changed;
        $new->segmentName = $name;
        $new->data[$name] ??= [];
        return $new;
    }

    /**
     * @param array<string,mixed> $options
     * @param null|array{lifetime?: int, path?: string, domain?: string|null, secure?: bool, httponly?: bool, samesite?: string} $cookieParams
     * @return void
     */
    function init(array $options = [], bool $autoCommit = false, ?array $cookieParams = null)
    {
        if ($this->init) {
            throw new \LogicException(\sprintf(
                '(%s) Reinitialization',
                __METHOD__,
            ));
        }
        if ($cookieParams !== null) {
            \session_set_cookie_params($cookieParams);
        }
        \session_start($options);
        $sesName = \session_name();
        $sesID   = \session_id();
        if (\is_string($sesName) && \is_string($sesID)) {
            $this->name        = $sesName;
            $this->id          = $sesID;
            $_COOKIE[$sesName] = $sesID;
        }
        $this->init       = true;
        $this->autoCommit = $autoCommit;
        $this->data       = $_SESSION;
        $this->data[$this->segmentName] ??= [];
    }

    /**
     * name=ID
     * @return string
     */
    function SID(): string
    {
        if ($this->name === null || $this->id === null) return '';
        return $this->name . '=' . $this->id;
    }

    /**
     * @return string
     */
    function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * @return string
     */
    function getID(): string
    {
        return $this->id ?? '';
    }

    /**
     * session_get_cookie_params | 
     * https://www.php.net/manual/ru/function.session-get-cookie-params.php
     * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
     */
    function getCookieParams(): array
    {
        return \session_get_cookie_params();
    }

    /**
     * Determine if any of the given keys are present and not null.
     *
     * @param  int|string|array<int|string>  $key
     */
    function hasAny($key): bool
    {
        return \sizeof(\array_filter(
            \is_array($key) ? $key : [$key],
            function ($key) {
                return $this->has($key);
            }
        )) >= 1;
    }

    /**
     * @param int|string $key
     */
    function has($key): bool
    {
        $this->data[$this->segmentName] ??= [];
        return \array_key_exists($key, $this->data[$this->segmentName]);
    }

    /**
     * очистить все в текущем сегменте
     * @return void
     */
    function flush()
    {
        $this->data[$this->segmentName] = [];
        $this->changed = true;
    }

    /**
     * Очистить все во всех сегментах
     * @return void
     */
    function flushAll()
    {
        $this->data    = [];
        $this->changed = true;
    }

    /**
     * записать с заменой
     * @param mixed $value
     * @return void
     */
    function put(string $name, $value)
    {
        $this->data[$this->segmentName][$name] = $value;
        $this->changed = true;
    }

    /**
     * записывает массив в корень сегмента с заменой
     *
     * @param mixed[] $value
     * @return void
     */
    function putInRoot(array $value)
    {
        $this->data[$this->segmentName] = $value;
        $this->changed = true;
    }

    /**
     * @param mixed $value
     * @return self
     */
    function push(string $name, $value)
    {
        $oldValue = $this->get($name, []);
        if (!\is_array($oldValue)) $oldValue = [$oldValue];
        $oldValue[] = $value;
        $this->put($name, $oldValue);
        $this->changed = true;
        return $this;
    }

    /**
     * @return mixed[]
     */
    function all()
    {
        return $this->data[$this->segmentName] ?? [];
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    function pull(string $name, $default = null)
    {
        $t = $this->get($name, $default);
        $this->remove($name);
        $this->changed = true;
        return $t;
    }

    /**
     * @return self
     */
    function increment(string $name, int $incrementBy = 1)
    {
        if (!$this->has($name)) {
            $this->put($name, 0);
        } else {
            $this->data[$this->segmentName][$name] += $incrementBy;
        }
        $this->changed = true;
        return $this;
    }

    /**
     * @return self
     */
    function decrement(string $name, int $decrementBy = 1)
    {
        if (!$this->has($name)) {
            $this->put($name, 0);
        } else {
            $this->data[$this->segmentName][$name] -= $decrementBy;
        }
        $this->changed = true;
        return $this;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    function get(string $name, $default = null)
    {
        if (!$this->has($name)) {
            if (\is_callable($default)) return \call_user_func($default);
            return $default;
        } else {
            return $this->data[$this->segmentName][$name];
        }
    }

    /**
     * @return void
     */
    function remove(string $name)
    {
        if ($name === '') return;
        unset($this->data[$this->segmentName][$name]);
        $this->changed = true;
    }

    /**
     * @param string|string[] $name
     * @return void
     */
    function forget($name)
    {
        if (\is_array($name)) {
            \array_map(
                fn($n) => $this->remove(\strval($n)),
                $name
            );
        } else $this->remove($name);
    }

    function status(): bool
    {
        return $this->init;
    }

    /**
     * @return void
     */
    function destroy()
    {
        $this->data = [];
        $name = \session_name();
        if (\is_string($name)) {
            \setcookie($name, '', (\time() - (3600 * 24)), '/');
            unset($_COOKIE[$name]);
        }
        $_SESSION = [];
        @\session_destroy();
        \session_write_close();
    }

    /**
     * @return void
     */
    function commit()
    {
        if ($this->changed) {
            $this->changed = false;
            $_SESSION = $this->data;
            \session_write_close();
        }
    }

    function __destruct()
    {
        if ($this->autoCommit) {
            $this->commit();
        }
    }
}
