<?php
class Hook
{
    private static $listeners = array();

    /**
     * Register a listener for an event. Lower priority runs first.
     *
     * @param string $event
     * @param callable $callback
     * @param int $priority
     * @return void
     */
    public static function listen($event, $callback, $priority = 10)
    {
        self::$listeners[$event][] = array('cb' => $callback, 'p' => $priority);
    }

    /**
     * Run every listener for an event as an action.
     *
     * @param string $event
     * @param mixed ...$args
     * @return void
     */
    public static function fire($event, ...$args)
    {
        foreach (self::sorted($event) as $l) {
            call_user_func_array($l['cb'], $args);
        }
    }

    /**
     * Thread a value through every listener and return the result.
     *
     * @param string $event
     * @param mixed $value
     * @param mixed ...$ctx
     * @return mixed
     */
    public static function filter($event, $value, ...$ctx)
    {
        foreach (self::sorted($event) as $l) {
            $value = call_user_func_array($l['cb'], array_merge(array($value), $ctx));
        }
        return $value;
    }

    /**
     * Return the first non-null listener result, else null.
     *
     * @param string $event
     * @param mixed ...$args
     * @return mixed|null
     */
    public static function until($event, ...$args)
    {
        foreach (self::sorted($event) as $l) {
            $r = call_user_func_array($l['cb'], $args);
            if ($r !== null) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Whether any listener is registered for an event.
     *
     * @param string $event
     * @return bool
     */
    public static function has($event)
    {
        return !empty(self::$listeners[$event]);
    }

    private static function sorted($event)
    {
        if (empty(self::$listeners[$event])) {
            return array();
        }
        $list = self::$listeners[$event];
        usort($list, function ($a, $b) {
            return $a['p'] - $b['p'];
        });
        return $list;
    }
}
