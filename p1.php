<?php
// Cho phép JavaScript từ các nguồn khác gọi vào (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Nếu là request kiểm tra kết nối OPTIONS thì dừng lại luôn
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../servicer/groqservice.php';
require_once __DIR__ . '/../servicer/firebaservice.php';
require_once __DIR__ . '/../servicer/systemservice.php';

use function servicer\systemservice\firestoreToTreeText;
use function servicer\systemservice\splitText2000;
use function servicer\systemservice\fsValue;
use servicer\firebaservice\firebaservice;
use servicer\groqservice\GroqService;

$fi = new firebaservice();
$groq = new GroqService();

// Lấy dữ liệu JSON gửi lên từ JavaScript
$inputJSON = file_get_contents('php://input');
$update = json_decode($inputJSON, true);

// Kiểm tra dữ liệu gửi lên từ JS (Yêu cầu phải có message và userId)
if (!isset($update['message']) || !isset($update['userId'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing message or userId"]);
    exit;
}

$user_id = $update['userId'];
$user_chat = $update['name'] ?? 'Web User';
$userMsg = $update['message'];

// Khởi tạo các bản ghi cơ bản trên Firebase nếu chưa có
$fi->set('user', $user_id, [
    'fields' => [
        'id' => ['stringValue' => (string)$user_id],
        'name' => ['stringValue' => (string)$user_chat],
        'email' => ['stringValue' => 'none'],
        'phone' => ['stringValue' => 'none'],
        'createdAt' => ['timestampValue' => date('c')]
    ]
]);

$fi->set('transactions', $user_id, ['fields' => ['userId' => ['stringValue' => (string)$user_id]]]);
$fi->set('category', $user_id, ['fields' => ['userId' => ['stringValue' => (string)$user_id]]]);
$fi->set('log', $user_id, ['fields' => ['userId' => ['stringValue' => (string)$user_id]]]);

// Xử lý AI phân tích dữ liệu giao dịch
$quesion = $groq->GenerateAsync($userMsg, "parse natural language spending entries into structured financial transactions, 'ngayThangNam': detecting date, 'danhMuc': category, 'loai': type (revenue/expenditure) and 'soTien': amount. If not, return 'none'");

$category = $fi->get('category', $user_id);

$data = [
    "userId" => ['integerValue' => (int)$user_id],
    "date" => ['stringValue' => (string)$groq->GenerateAsync($quesion, "Analyze text for date format dd/MM/yyyy, else 'none'. Example: today is " . date('d/m/Y') . ", 'Hôm nay' => '" . date('d/m/Y') . "'")],
    "categoryId" => ['stringValue' => (string)$groq->GenerateAsync($quesion, "Prioritize item matching from {data}. Return short string description or 'none'. {data} :" . firestoreToTreeText($category))],
    "type" => ['stringValue' => (string)$groq->GenerateAsync($quesion, "Analyze expenditure type: 'thu' for revenue, 'chi' for expense, else 'none'.")],
    "amount" => ['integerValue' => (int)$groq->GenerateAsync($quesion, "Analyze amount to integer, e.g., '10k' => 10000, else 'none'.")],
    "currency" => ['stringValue' => (string)$groq->GenerateAsync($quesion, "Identify currency code (VND, USD...), else 'none'.")],
    "note" => ['stringValue' => (string)$userMsg],
    "paymentMethod" => ['stringValue' => (string)$groq->GenerateAsync($quesion, "Identify payment method, else 'TIỀN MẶT'.")],
    "createdAt" => ['stringValue' => (string)date("c")],
    "nguon" => ['stringValue' => (string)($user_chat . ' - ' . $user_id)],
    "source" => ['stringValue' => "web_frontend"],
    "username" => ['stringValue' => (string)$user_chat]
];

// Xử lý truy vấn dữ liệu dạng câu hỏi (bắt đầu bằng dấu ?)
if (substr($userMsg, 0, 1) === "?") {
    $searchMsg = ltrim(mb_substr($userMsg, 1));
    $get = $fi->get('transactions', $user_id);
    $a = firestoreToTreeText($get);
    
    $query = $groq->GenerateAsync($searchMsg, "Answer the question based entirely on data. Return in Vietnamese only. data: $a");
    
    echo json_encode([
        "status" => "success",
        "type" => "query",
        "reply" => $query
    ]);
    exit;
}

// Kiểm tra điều kiện dữ liệu thiếu
if ($data['categoryId']['stringValue'] == "none" || $data['amount']['integerValue'] == '0' || $data['type']['stringValue'] == "none") {
    $rep = $groq->GenerateAsync($userMsg, "Gemini AI placeholder");
    echo json_encode([
        "status" => "success",
        "type" => "chat",
        "reply" => $rep
    ]);
    exit;
}

// Lưu danh mục mới nếu chưa có
$index = 0;
if (isset($category['fields'])) {
    foreach ($category['fields'] as $key => $item) {
        if (isset($item['stringValue']) && $item['stringValue'] == $data['categoryId']['stringValue']) {
            $index++;
        }
    }
    $length = count($category['fields']);
} else {
    $length = 0;
}

if ($index == 0) {
    $fi->update('category', $user_id, [
        'fields' => [
            'item' . $length => ['stringValue' => (string)$data['categoryId']['stringValue']]
        ]
    ]);
}

// Tạo document tạm để lấy ID tự động
$res = $fi->set('test', null, ['fields' => ['msg' => ['stringValue' => 'Hello World']]]);
$fullName = $res['name'];
$docId = basename($fullName); 
$data['id'] = ['stringValue' => (string)$docId];
$fi->delete('test', $docId);

$rep = $groq->GenerateAsync($userMsg, "save data successfully " . print_r($data, true));

// Cập nhật giao dịch vào Firebase
$fi->update('transactions', $user_id, [
    'fields' => [
        $data['id']['stringValue'] => [
            'mapValue' => ['fields' => $data]
        ]
    ]
]);

// Lưu Log
$fi->update('log', $user_id, [
    'fields' => [
        $data['id']['stringValue'] => [
            'mapValue' => [
                'fields' => [
                    "createdAt" => ['stringValue' => (string)date("c")],
                    "message" => ['stringValue' => (string)$userMsg],
                    "output" => ['stringValue' => (string)$rep],
                ]
            ]
        ]
    ]
]);

// Phản hồi kết quả về cho JavaScript
echo json_encode([
    "status" => "success",
    "type" => "saved",
    "reply" => $rep,
    "transaction" => $data
]);