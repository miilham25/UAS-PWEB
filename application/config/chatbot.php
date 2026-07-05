<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$env_file = FCPATH . '.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$config['grok_api_key'] = getenv('GROQ_API_KEY') ?: '';
$config['grok_api_url'] = 'https://api.groq.com/openai/v1/chat/completions';
$config['grok_model']   = 'llama-3.3-70b-versatile';

$config['gemini_api_key'] = getenv('GEMINI_API_KEY') ?: ''; 
$config['gemini_api_url'] = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';