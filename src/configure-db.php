#!/usr/bin/env php
<?php

$confpath = '/var/www/config.php';

$config = array();

$config['SELF_URL_PATH'] = env('SELF_URL_PATH', 'http://localhost');
$config['DB_TYPE'] = 'pgsql';
$config['DB_HOST'] = env('DB_HOST', 'postgres');
$config['DB_PORT'] = env('DB_PORT', 5432);
$config['DB_NAME'] = env('DB_NAME', 'ttrss');
$config['DB_USER'] = env('DB_USER');
$config['DB_PASS'] = env('DB_PASS');
$config['PLUGINS'] = env('ENABLE_PLUGINS','auth_internal,fever');
$config['SESSION_COOKIE_LIFETIME'] = env('SESSION_COOKIE_LIFETIME', 24) * 3600;

if(dbcheckconn($config)){
    $pdo = dbconnect($config);

    if(!dbcheckdb($config)){

        echo 'Database not found, creating.'. PHP_EOL ;

        $pdo = dbconnect($config);
        $pdo -> exec('CREATE DATABASE ' . ($config['DB_NAME']) . ' WITH OWNER ' . ($config['DB_USER']));

        unset($pdo);

        $pdo = dbexist($config);
        try {
            $pdo->query('SELECT 1 FROM ttrss_feeds');
        }
        catch (PDOException $e) {
            echo 'Database table not found, applying schema... ' . PHP_EOL;
            $schema = file_get_contents('schema/ttrss_schema_' . $config['DB_TYPE'] . '.sql');
            $schema = preg_replace('/--(.*?);/', '', $schema);
            $schema = preg_replace('/[\r\n]/', ' ', $schema);
            $schema = trim($schema, ' ;');
            foreach (explode(';', $schema) as $stm) {
                $pdo->exec($stm);
            }
            unset($pdo);
        }
    }
    $contents = file_get_contents($confpath);
    foreach ($config as $name => $value) {
        $contents = preg_replace('/(define\s*\(\'' . $name . '\',\s*)(.*)(\);)/', '$1"' . $value . '"$3', $contents);
    }

    if (getenv('HTTP_PROXY') !== false) {
        $contents .= "\r\n\t";
        $contents .= "define('_HTTP_PROXY', '"  . env('HTTP_PROXY') . "');";
    }

    file_put_contents($confpath, $contents);
}


function env($name, $default = null)
{
    $v = getenv($name) ?: $default;
    
    if ($v === null) {
        error('The env ' . $name . ' does not exist'). PHP_EOL ;
    }
    
    return $v;
}

function error($text)
{
    echo 'Error: ' . $text . PHP_EOL;
    exit(1);
}

function dbconnect($config)
{
    $map = array('host' => 'HOST', 'port' => 'PORT');
    $dsn = $config['DB_TYPE'] . ':';
    foreach ($map as $d => $h) {
        if (isset($config['DB_' . $h])) {
            $dsn .= $d . '=' . $config['DB_' . $h] . ';';
        }
    }
    $pdo = new \PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function dbcheckconn($config)
{
    try {
        dbconnect($config);
        return true;
    }
    catch (PDOException $e) {
        return false;
    }
}

function dbexist($config)
{
    $map = array('host' => 'HOST', 'port' => 'PORT' , 'dbname' =>'NAME');
    $dsn = $config['DB_TYPE'] . ':';
    foreach ($map as $d => $h) {
        if (isset($config['DB_' . $h])) {
            $dsn .= $d . '=' . $config['DB_' . $h] . ';';
        }
    }
    $pdo = new \PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function dbcheckdb($config)
{
    try {
        dbexist($config);
        return true;
    }
    catch (PDOException $e) {
        return false;
    }
}
