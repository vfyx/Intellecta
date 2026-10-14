<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string
{
    return strtoupper(substr(trim($name), 0, 1));
}
