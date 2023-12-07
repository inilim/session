<?php

namespace Inilim\Session;

class Session
{
   private const NAME           = '_main';
   private string $segment_name = self::NAME;
   private bool $init           = false;
   private bool $changed        = false;
   private bool $auto_commit    = false;
   /**
    * @var mixed[][]
    */
   private array $data          = [];

   public function segment(string $name): self
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
    * @param array<string,mixed> $option
    */
   public function init(array $option = [], bool $auto_commit = false, ?string $domain = null): void
   {
      if ($this->init) throw new \LogicException(self::class . ' Повторная инициализация');
      if (is_string($domain)) session_set_cookie_params(0, '/', '.' . $domain);
      session_start($option);
      $ses_name = session_name();
      $ses_id   = session_id();
      if (is_string($ses_name) && is_string($ses_id)) {
         $_COOKIE[$ses_name] ??= $ses_id;
      }
      $this->init        = true;
      $this->auto_commit = $auto_commit;
      $this->data        = $_SESSION;
      $this->data[$this->segment_name] ??= [];
   }

   public function has(string $name): bool
   {
      return isset($this->data[$this->segment_name][$name]);
   }

   /**
    * очистить все в текущем сегменте
    */
   public function flush(): void
   {
      $this->data[$this->segment_name] = [];
      $this->changed = true;
   }

   public function put(string $name, mixed $value): void
   {
      $this->data[$this->segment_name][$name] = $value;
      $this->changed = true;
   }

   public function push(string $name, mixed $value): self
   {
      $values = $this->get($name, []);
      if (!is_array($values)) $values = [$values];
      $values[] = $value;
      $this->put($name, $values);
      $this->changed = true;
      return $this;
   }

   /**
    * @return mixed[]|array{}
    */
   public function all(): array
   {
      return $this->data[$this->segment_name] ?? [];
   }

   public function pull(string $name, mixed $default = null): mixed
   {
      $t = $this->get($name, $default);
      $this->remove($name);
      $this->changed = true;
      return $t;
   }

   public function increment(string $name, int $increment_by = 1): self
   {
      if (!$this->has($name)) {
         $this->put($name, 0);
      } else {
         $this->data[$this->segment_name][$name] += $increment_by;
      }
      $this->changed = true;
      return $this;
   }

   public function decrement(string $name, int $decrement_by = 1): self
   {
      if (!$this->has($name)) {
         $this->put($name, 0);
      } else {
         $this->data[$this->segment_name][$name] -= $decrement_by;
      }
      $this->changed = true;
      return $this;
   }

   public function get(string $name, mixed $default = null): mixed
   {
      if (!$this->has($name)) {
         if (is_callable($default)) return $default();
         return $default;
      } else {
         return $this->data[$this->segment_name][$name];
      }
   }

   public function remove(string $name): void
   {
      if ($name === '') return;
      unset($this->data[$this->segment_name][$name]);
      $this->changed = true;
   }

   /**
    * @param string|string[] $name
    * @return void
    */
   public function forget(string|array $name): void
   {
      if (is_array($name)) array_map(fn ($name) => $this->remove(strval($name)), $name);
      else $this->remove($name);
   }

   public function status(): bool
   {
      return $this->init;
   }

   public function destroy(): void
   {
      $name = session_name();
      if (is_string($name)) {
         setcookie($name, '', (time() - (3600 * 24)), '/');
         unset($_COOKIE[$name]);
      }
      $_SESSION = [];
      @session_destroy();
      session_write_close();
   }

   public function commit(): void
   {
      if ($this->changed) {
         $this->changed = false;
         $_SESSION = $this->data;
         session_write_close();
      }
   }

   public function __destruct()
   {
      if ($this->auto_commit) {
         $this->commit();
      }
   }
}
