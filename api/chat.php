<?php
ob_start();

header('Content-Type: application/json; charset=utf-8');

function sendError($code, $msg)
{
    ob_clean();
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// Load .env from project root
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

$apiKey = $_ENV['GROQ_API_KEY'] ?? '';
if (!$apiKey) {
    sendError(500, 'API key no configurada. Sube el fichero .env al servidor.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method Not Allowed');
}

if (!function_exists('curl_init')) {
    sendError(500, 'cURL no está habilitado en este servidor PHP.');
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['messages']) || !is_array($data['messages'])) {
    sendError(400, 'Formato de petición inválido');
}

$systemPrompt = "Eres el asistente virtual inteligente de 'VitalSport', una clínica de fisioterapia avanzada situada en Algemesí, Valencia.
Tu objetivo es ayudar a los visitantes de la web respondiendo a sus preguntas, dando información sobre los servicios y facilitando el contacto o reserva de citas.

### DATOS DE LA CLÍNICA:
- **Nombre:** VitalSport
- **Dirección:** C/ Covadonga, 13B, Algemesí (Valencia), 46680.
- **Teléfono / WhatsApp:** +34 614 07 96 81
- **Email:** vitalsportfisioterapia@gmail.com
- **Horario:** Lunes a viernes de 9:00 a 20:00 horas.

### SERVICIOS PRINCIPALES:
- **Fisioterapia Deportiva:** Recuperación de movilidad, alivio del dolor, técnicas manuales y avanzadas.
- **Rehabilitación:** Post-quirúrgica y post-lesión con protocolos científicos.
- **Ejercicio Terapéutico:** Programas personalizados para fortalecer y prevenir lesiones de forma segura.
- **Otros servicios:** Terapia manual, readaptación, ecografía, presoterapia.

### REGLAS DE COMPORTAMIENTO:
1. Tono amable, empático y profesional. Habla en el idioma del usuario.
2. Para citas, invita a reservar en la web o llama al +34 614 07 96 81.
3. Si no sabes algo médico específico, deriva al fisioterapeuta.
4. Respuestas cortas y directas. Usa emojis ocasionalmente 🏃‍♂️💪.";

$messages = $data['messages'];
array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'llama-3.3-70b-versatile',
    'messages' => $messages,
    'temperature' => 0.6,
    'max_tokens' => 500
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    sendError(500, "Error cURL: $curlError");
}

$decoded = json_decode($response, true);

if ($httpCode >= 400) {
    $msg = $decoded['error']['message'] ?? "Groq API error $httpCode";
    sendError($httpCode, $msg);
}

if (!isset($decoded['choices'][0]['message']['content'])) {
    sendError(500, 'Respuesta inesperada de Groq: ' . $response);
}

ob_clean();
echo json_encode(['reply' => $decoded['choices'][0]['message']['content']]);
