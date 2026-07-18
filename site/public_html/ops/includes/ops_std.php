<?php
function stderr() 
{
    return defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
}
function stdout() 
{
    return defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
}
