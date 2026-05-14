<?php
header('Content-Type: application/json; charset=utf-8');

// Load .env from project root
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

$apiKey = $_ENV['GROQ_API_KEY'] ?? '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(["error" => "API key not configured"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['messages']) || !is_array($data['messages'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request format"]);
    exit;
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

### REGLAS DE COMPORTAMIENTO (MUY IMPORTANTE):
1. **Tono:** Sé amable, empático, profesional y muy cercano. Habla siempre en el idioma en el que te hablen (español o valenciano preferiblemente).
2. **Citas:** No tienes acceso a la agenda en tiempo real. Si alguien quiere pedir cita o preguntar por disponibilidad, invítale a reservar directamente en la web (usando el botón 'Reservar Ahora') o dale el número de teléfono/WhatsApp (+34 614 07 96 81) indicándole que allí se lo confirmarán al instante.
3. **No inventes:** Si no sabes la respuesta o te preguntan algo médico/diagnóstico específico, indica con educación que cada caso es único y que es mejor que contacten por teléfono o WhatsApp para que el fisioterapeuta les asesore directamente.
4. **Respuestas cortas:** Sé conciso y directo, ideal para leer en un móvil. Evita párrafos muy largos. Utiliza emojis ocasionalmente para dar cercanía 🏃‍♂️💪🙌.";

$messages = $data['messages'];

array_unshift($messages, [
    "role" => "system",
    "content" => $systemPrompt
]);

$url = "https://api.groq.com/openai/v1/chat/completions";

$postData = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => $messages,
    "temperature" => 0.6,
    "max_tokens" => 500
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $apiKey,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(["error" => "Error connecting to AI service"]);
    exit;
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    $responseData = json_decode($response, true);
    $errorMsg = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'API Error';
    echo json_encode(["error" => $errorMsg]);
    exit;
}

$responseData = json_decode($response, true);
if (isset($responseData['choices'][0]['message']['content'])) {
    $aiReply = $responseData['choices'][0]['message']['content'];
    echo json_encode(["reply" => $aiReply]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Invalid response format from AI service"]);
}
