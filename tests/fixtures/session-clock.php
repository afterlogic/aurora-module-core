<?php

namespace Aurora\System;

final class SessionTestClock
{
    public static int $now = 1700000000;
}

function time(): int
{
    return SessionTestClock::$now;
}
