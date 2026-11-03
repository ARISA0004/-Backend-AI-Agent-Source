<?php
require_once __DIR__ . '/../servicer/groqservice.php';
require_once __DIR__ . '/../servicer/teleservicer.php';
require_once __DIR__ . '/../servicer/firebaservice.php';
require_once __DIR__ . '/../servicer/systemservice.php';

use function servicer\systemservice\firestoreToTreeText;
use function servicer\systemservice\splitText2000;
use function servicer\systemservice\fsValue;
use servicer\firebaservice\firebaservice;
use servicer\teleservicer\teleservicer;
use servicer\groqservice\GroqService;

$fi = new firebaservice();
$tele = new teleservicer();
$groq = new GroqService();
$update = json_decode(file_get_contents('php://input'), true);
//error_log(date("Y-m-d H:i:s") .  print_r($update, true), 3, __DIR__ . "/app.log");

    if (!isset($update['message']['text'])) {
        exit;
    }
    $user_chat = $update['message']['from']['first_name'] . ' ' . ($update['message']['from']['last_name'] ?? '');
    $user_id = $update['message']['from']['id'];
    $chatId = $update['message']['chat']['id'];
    $userMsg = $update['message']['text'];

$fi->set(
    'user',
    $user_id,
     $data = [
    'fields' => [
        'id' => ['stringValue' => (string)$user_id],
        'name' => ['stringValue' => (string)$user_chat],
        'email' => ['stringValue' => 'none'],
        'phone' => ['stringValue' => 'none'],
        'createdAt' => ['timestampValue' => date('c')]
        
    ]]
);
$fi ->set(
    'transactions',
    $user_id,
     $data = [
    'fields' => [
        'userId' => ['stringValue' => (string)$user_id],
    ]]
);
$fi ->set(
    'category',
    $user_id,
     $data = [
    'fields' => [
        'userId' => ['stringValue' => (string)$user_id],
    ]]
);
$fi ->set(
    'log',
    $user_id,
     $data = [
    'fields' => [
        'userId' => ['stringValue' => (string)$user_id],
    ]]
);
error_log( firestoreToTreeText($category), 3, __DIR__ . "/app.log");
    $rep = $groq->GenerateAsync($userMsg, "Gemini AI placeholder");
    $reply = splitText2000($rep);
    $quesion = $groq->GenerateAsync($userMsg, "parse natural language spending entries into structured financial transactions,'ngayThangNam': detecting date,'danhMuc': category (e.g. name item) ,'loai': type (revenue/expenditure) and 'soTien': amount. If not, return 'none'");
    $category =$fi->get(
                'category',
                $user_id,
            );
     $data = array(
            "userId" => ['integerValue' => (int)$update['message']['from']['id']],
            "date" => ['stringValue' => (string)$groq->GenerateAsync($quesion,
            "
            Analyze the text and extract the date if it contains time-related words.
            - Output format: dd/MM/yyyy
            - If no date-related words are present, return 'none'
            - Example: date now is 20/11/2025 
            'Hôm nay' => '20/11/2025'
            "
            )],

            "categoryId" => ['stringValue' => (string)$groq->GenerateAsync($quesion,
           "The text will be processed to extract the name of the spending item, prioritizing selection from the list of items {data} that match the text; if no matching item is found, the item will be inferred from the item name in the text.
            - Prioritize selection from the list of items {data} that match the text
            - The returned result must be a short string describing the item
            - Example: 'Stationery purchases' => 'stationery'
            - If no condition is met, return 'none'

     {data} :" . firestoreToTreeText($category)
            )],
            "type" => ['stringValue' => (string)$groq->GenerateAsync($quesion,
            "
            Analyze the text for expenditure type.
            - If it is revenue, return 'thu'
            - If it is expense, return 'chi'
            - If unclear or not related, return 'none'
            - Example: 'Bán nhà' => 'thu', 'Mua bàn ghế' => 'chi'
            "
            )],
            "amount" => ['integerValue' => (int)$groq->GenerateAsync($quesion,
            "
            Analyze the text for any amount mentioned.
            - Convert to integer, e.g. '10k' => 10000, '15 triệu' => 15000000
            - If no amount is present, return 'none'
            - Example: 'Bán nhà 15 tỷ' => 15000000000
            "
            )],
            "currency" => ['stringValue' => (string)$groq->GenerateAsync($quesion,
            "You are an AI specializing in currency classification.
            - Analyze text to identify valid currencies, e.g., VND (Vietnamese Dong), USD (US Dollar), EUR (Euro), JPY (Japanese Yen), GBP (British Pound), CNY (Chinese Yuan), KRW (Korean Won), SGD (Singapore Dollar), AUD (Australian Dollar), ...
            - Only return currency code such as USD.
            - No explanation.
            - No additional text.
            - Prioritize the first currency symbol ($, ₫, €, ¥, £) if it appears.
            - Prioritize abbreviations (dong, USD, EUR...) if they appear.
            - If none is found, return none.
            - Example: 'nhận được 15k' -> 'VND'"
            )],
            "note" => ['stringValue' => (string)$userMsg],
            "paymentMethod" => ['stringValue' => (string)$groq->GenerateAsync($quesion,
            "
            You are an AI system specializing in payment method analysis.
            Analyze text to identify the payment method used, such as:TIỀN MẶT, CHUYỂN KHOẢN NGÂN HÀNG, THẺ NGÂN HÀNG, VÍ ĐIỆN TỬ, MÃ QR, THANH TOÁN TRỰC TUYẾN, THANH TOÁN KHI NHẬN HÀNG (COD), THANH TOÁN TRẢ GÓP
            - Return only **1 payment method**.
            - No explanation.
            - No additional text.
            - Prioritize clear keywords (e.g., “tiền mặt”, “chuyển khoản ngân hàng”, “Momo”, “VNPay”, “quẹt thẻ”, “QR”). 
            - If multiple payment methods are used, select the **primary** method.
            - For example, 'bank 15k' -> 'chuyển khoản ngân hàng', 'Momo 200k' -> 'ví điện tử'
            - If none is found , return 'tiền mặt'.
            ")],
            "createdAt" => ['stringValue' => (string)date("c")],
            "nguon" => ['stringValue' =>     (string)($update['message']['from']['first_name'] . ' ' . $update['message']['from']['last_name'] . ' - ' . $update['message']['from']['id'])],
            "source" => ['stringValue' =>    "console_telegram_bot"],
            "username" => ['stringValue' =>  (string)$user_chat] 
        );
  

// query 
    if (substr($userMsg, 0, 1) === "?")
        {
            $text = $update['message']['text'] ?? '';
            $userMsg = ltrim(mb_substr($text, 1));
            $get= $fi->get(
                'transactions',
                $user_id,
            );
            $a =firestoreToTreeText($get);
            $query = $groq->GenerateAsync($userMsg, 
            "Your task:
                - Answer the question based entirely on the above data.
                - Filter by day/month asked but '2025-11-18T17:00:00.000Z' = '2025-11-19'.
                - 'buy what', 'spend what', 'spend what' → filter type = 'spend'
                - 'get what', 'get what' → filter type = 'get'
                - Answer by spending direction e.g. 'what day was yesterday' → 'yesterday was the day you spent 50000 on noodles' or 'Yesterday was the day you sold newspapers and earned 100,000.', 'my income' → 'yesterday you got 50000 from birthday,...'
                - If there is no data, answer without data
                - Do not explain anything, do not add additional information.
                - Return answer in Vietnamese language only.
                - record watching them
                data:
                " .$a.  "
                question: " . $data ."
                ");
            error_log(date("Y-m-d H:i:s") . " :1: " . $query, 3, __DIR__ . "/app.log");
            //$tele ->tele($update,print_r(array_values($get["fields"])[0], true));
            
            $tele ->tele($update,$query);   
        exit;
        }
// warning 
    if (substr($userMsg, 0, 1) === "!")
        {
            $text = $update['message']['text'] ?? '';
            $userMsg = ltrim(mb_substr($text, 1));
            $get= $fi->get(
                'transactions',
                $user_id,
            );
            error_log(date("Y-m-d H:i:s") . " :1: " . print_r($get["fields"], true), 3, __DIR__ . "/app.log");
            //$tele ->tele($update,print_r(array_values($get["fields"])[0], true));
            $string = "

            ";
            foreach ($get["fields"] as $key => $value) {
            $tele ->tele($update,$key.": ".print_r($value, true));   
            }
            exit;
        }

// start
        if ($data['categoryId']['stringValue'] == "none" || $data['amount']['integerValue'] == '0' || $data['type']['stringValue'] == "none") {
            error_log(date("Y-m-d H:i:s") . " Exit here
            ", 3, __DIR__ . "/app.log");
            foreach ($reply as $mes) {
            $log = $tele->tele($update,$mes);
            error_log(date("Y-m-d H:i:s") . " Data: " .$mes, 3, __DIR__ . "/app.log");
            }
            exit;
        }
        $index = 0;
        foreach ($category['fields'] as $key => $item) {
                
            if ($item['stringValue'] == $data['categoryId']['stringValue']) {
                $index++;
            }
        }
        $length = count($category['fields']);
        if ($index == 0) {
            $res = $fi->update(
                'category',
                $user_id,
                $dates = [
                'fields' => [
                'item'. $length => ['stringValue' => (string)$data['categoryId']['stringValue']],
                ]
            ]
            );
            error_log(date("Y-m-d H:i:s") . " Creating category ".$length.": " . $data['categoryId']['stringValue'] . print_r($res, true), 3, __DIR__ . "/app.log");
        }
        if ($data['date']['stringValue'] == "none") { $data['date']['stringValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze the text to determine if the date is in dd/MM/yyyy format; otherwise, return 'null'."); }
        if ($data['categoryId']['stringValue'] == "none") { $data['categoryId']['stringValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze the text to category item name expense/income e.g. 'Mua sắm văn phòng phẩm' => 'văn phòng phẩm',if not return 'null'"); }
        if ($data['type']['stringValue'] == "none") { $data['type']['stringValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze the text for expenditure type only select 'chi' or 'thu', if not return 'null'."); }
        if ($data['amount']['integerValue'] == "none") { $data['amount']['integerValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze the text for any amount mentioned. Convert to integer  Example: 'Bán nhà 15 tỷ' => 15000000000, if not return 'null'."); }
        if ($data['currency']['stringValue'] == "none") { $data['currency']['stringValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze text to identify valid currencies, e.g., VND (Vietnamese Dong), USD (US Dollar), EUR (Euro),..., e.g. '15k' -> VND, '5 đô' -> USD. If none is found, return 'null'."); }
        if ($data['paymentMethod']['stringValue'] == "none") { $data['paymentMethod']['stringValue'] = $groq->GenerateAsync($data['note']['stringValue'], "Analyze text to identify the payment method used, such as:TIỀN MẶT, CHUYỂN KHOẢN NGÂN HÀNG, THẺ NGÂN HÀNG, VÍ ĐIỆN TỬ, MÃ QR, THANH TOÁN TRỰC TUYẾN, THANH TOÁN KHI NHẬN HÀNG (COD), THANH TOÁN TRẢ GÓP e.g. 'bank' -> 'chuyển khoản ngân hàng' . If none is found, return 'TIỀN MẶT'."); }
        $res = $fi->set(
                'test',
                null,
                $dates = [
                'fields' => [
                    'msg' => ['stringValue' => 'Hello World'],
                ]
            ]
            );
       

        
        $fullName = $res['name'];
        $docId = basename($fullName); 
        $data['id'] =  ['stringValue' => (string)$docId];
        $fi->delete('test', $docId);
        $rep = $groq->GenerateAsync($userMsg, "save data successfully".print_r($data, true));
        $reply = splitText2000($rep);
        foreach ($reply as $mes) {
              $tele->tele($update,$mes);
            };
           
        
        $fi->update(
                'transactions',
                $user_id,
                $dates = [
                    'fields' => [
                        $data['id']['stringValue'] => [
                            'mapValue' => [
                            'fields' => $data
                            ]
                        ]
                    ]
                ]

            );
        $bur = $fi->update(
                'log',
                $user_id,
                $dates = [
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
                ]

            );
            error_log(date("Y-m-d H:i:s") . " Log: " . print_r($bur, true), 3, __DIR__ . "/app.log");
        


?>
