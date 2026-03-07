<?php

class IdGenerator {
    public static function generate(): string {
        return str_replace('.', '', microtime(true));  // z. B. 1715941749123456
    }
}
