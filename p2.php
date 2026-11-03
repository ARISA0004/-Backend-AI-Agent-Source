<?php
require_once __DIR__ . '/../servicer/groqservice.php';
use servicer\groqservice\GroqService;
$groq = new GroqService();
echo $groq->GenerateAsync("Hello Groq!", "You are a helpful assistant.");