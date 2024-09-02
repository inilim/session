<?php

namespace Inilim\Session;

class Session
{
   private const NAME           = '_main';
   /**
    * @var string
    */
   private $segment_name = self::NAME;
   /**
    * @var boolean
    */
   private $init           = false;
   /**
    * @var boolean
    */
   private $changed        = false;
   /**
    * @var boolean
    */
   private $auto_commit    = false;
   /**
    * @var string|null
    */
   private $name = null;
   /**
    * @var string|null
    */
   private $id   = null;
   /**
    * @var mixed[][]
    */
   private $data          = [];

   /**
    * @param string $name
    * @return self
    */
   function segment($name)
   {
      if ($name === self::NAME) return $this;
      $new               = new self;
      $new->data         = &$this->data;
      $new->init         = &$this->init;
      $new->changed      = &$this->changed;
      $new->segment_name = $name;
      $new->data[$name] ??= [];
      return $new;
   }

   /**
    * @param array<string,mixed> $options
    * @param bool $auto_commit
    * @param null|array $cookie_params
    * @return void
    */
   function init($options = [], $auto_commit = false, $cookie_params = null)
   {
      if ($this->init) throw new \LogicException(self::class . ' Повторная инициализация');
      if ($cookie_params !== null) {
         \session_set_cookie_params($cookie_params);
      }
      \session_start($options);
      $ses_name = \session_name();
      $ses_id   = \session_id();
      if (\is_string($ses_name) && \is_string($ses_id)) {
         $this->name = $ses_name;
         $this->id   = $ses_id;
         $_COOKIE[$ses_name] = $ses_id;
      }
      $this->init        = true;
      $this->auto_commit = $auto_commit;
      $this->data        = $_SESSION;
      $this->data[$this->segment_name] ??= [];
   }

   /**
    * name=ID
    * @return string|empty-string
    */
   function SID()
   {
      if ($this->name === null || $this->id === null) return '';
      return $this->name . '=' . $this->id;
   }

   /**
    * @return string|empty-string
    */
   function getName()
   {
      return $this->name ?? '';
   }

   /**
    * @return string|empty-string
    */
   function getID()
   {
      return $this->id ?? '';
   }

   /**
    * session_get_cookie_params | 
    * https://www.php.net/manual/ru/function.session-get-cookie-params.php
    * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
    */
   function getCookieParams()
   {
      return \session_get_cookie_params();
   }

   /**
    * Determine if any of the given keys are present and not null.
    *
    * @param  string|array  $key
    * @return bool
    */
   function hasAny($key)
   {
      return \sizeof(\array_filter(
         \is_array($key) ? $key : \func_get_args(),
         function ($key) {
            return $this->has($key);
         }
      )) >= 1;
   }

   /**
    *
    * @param string $key
    * @return bool
    */
   function has($key)
   {
      $this->data[$this->segment_name] ??= [];
      return \array_key_exists($key, $this->data[$this->segment_name]);
   }

   /**
    * очистить все в текущем сегменте
    *
    * @return void
    */
   function flush()
   {
      $this->data[$this->segment_name] = [];
      $this->changed = true;
   }

   /**
    * Очистить все во всех сегментах
    *
    * @return void
    */
   function flushAll()
   {
      $this->data = [];
      $this->changed = true;
   }

   /**
    * записать с заменой
    * @param string $name
    * @param mixed $value
    * @return void
    */
   function put($name, $value)
   {
      $this->data[$this->segment_name][$name] = $value;
      $this->changed = true;
   }

   /**
    * записывает массив в корень сегмента с заменой
    *
    * @param array $value
    * @return void
    */
   function putInRoot($value)
   {
      $this->data[$this->segment_name] = $value;
      $this->changed = true;
   }

   /**
    * @param string $name
    * @param mixed $value
    * @return self
    */
   function push($name, $value)
   {
      $old_value = $this->get($name, []);
      if (!\is_array($old_value)) $old_value = [$old_value];
      $old_value[] = $value;
      $this->put($name, $old_value);
      $this->changed = true;
      return $this;
   }

   /**
    * @return mixed[]|array{}
    */
   function all()
   {
      return $this->data[$this->segment_name] ?? [];
   }

   /**
    * @param string $name
    * @param mixed $default
    * @return mixed
    */
   function pull($name, $default = null)
   {
      $t = $this->get($name, $default);
      $this->remove($name);
      $this->changed = true;
      return $t;
   }

   /**
    * @param string $name
    * @param integer $increment_by
    * @return self
    */
   function increment($name, $increment_by = 1)
   {
      if (!$this->has($name)) {
         $this->put($name, 0);
      } else {
         $this->data[$this->segment_name][$name] += $increment_by;
      }
      $this->changed = true;
      return $this;
   }

   /**
    * @param string $name
    * @param integer $decrement_by
    * @return self
    */
   function decrement($name, $decrement_by = 1)
   {
      if (!$this->has($name)) {
         $this->put($name, 0);
      } else {
         $this->data[$this->segment_name][$name] -= $decrement_by;
      }
      $this->changed = true;
      return $this;
   }

   /**
    * @param string $name
    * @param mixed $default
    * @return mixed
    */
   function get($name, $default = null)
   {
      if (!$this->has($name)) {
         if (\is_callable($default)) return \call_user_func($default);
         return $default;
      } else {
         return $this->data[$this->segment_name][$name];
      }
   }

   /**
    * @param string $name
    * @return void
    */
   function remove($name)
   {
      if ($name === '') return;
      unset($this->data[$this->segment_name][$name]);
      $this->changed = true;
   }

   /**
    * @param string|string[] $name
    * @return void
    */
   function forget($name)
   {
      if (\is_array($name)) \array_map(fn($n) => $this->remove(\strval($n)), $name);
      else $this->remove($name);
   }

   /**
    * @return boolean
    */
   function status()
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
      if ($this->auto_commit) {
         $this->commit();
      }
   }
}
