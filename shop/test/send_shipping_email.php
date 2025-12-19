<?php
/**
 * 測試出貨通知信發送功能
 * 測試檔案: test_send_shipping_email.php
 * 建立日期: 2025-12-18
 * 位置: shop/test/
 */

define('IN_ECS', true);

// 設定字元編碼
header('Content-Type: text/html; charset=utf-8');

// 載入前台核心檔案 (避免後台登入驗證)
require(dirname(__FILE__) . '/../includes/init.php');

// 載入訂單相關函式庫
require(dirname(__FILE__) . '/../includes/lib_order.php');

// 載入 OrderOperate 類別
require(dirname(__FILE__) . '/../admin_Site168/includes/order/lib_order_operate.php');

// 強制重新載入設定 (避免使用快取)
$sql = "SELECT code, value FROM " . $ecs->table('shop_config');
$result = $db->query($sql);
while ($row = $db->fetchRow($result)) {
    $_CFG[$row['code']] = $row['value'];
}

// 測試結果陣列
$test_results = [];
$test_results['test_time'] = date('Y-m-d H:i:s');
$test_results['test_file'] = basename(__FILE__);

echo "<html><head><meta charset='utf-8'><title>測試出貨通知信</title>";
echo "<style>
    body { font-family: 'Microsoft JhengHei', Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 10px; }
    .info-box { background: #ecf0f1; padding: 15px; border-radius: 5px; margin: 15px 0; }
    .success { color: #27ae60; font-weight: bold; }
    .error { color: #e74c3c; font-weight: bold; }
    .warning { color: #f39c12; font-weight: bold; }
    pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
    th { background: #34495e; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .step { background: #3498db; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; margin: 10px 0; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🧪 出貨通知信測試程式</h1>";

// ============================================================================
// 步驟 1: 檢查系統配置
// ============================================================================
echo "<div class='step'>步驟 1: 檢查系統配置</div>";
echo "<div class='info-box'>";

$test_results['config_check'] = [
    'send_ship_email' => $_CFG['send_ship_email'],
    'shop_name' => $_CFG['shop_name'],
    'smtp_host' => $_CFG['smtp_host'] ?? 'N/A',
    'smtp_port' => $_CFG['smtp_port'] ?? 'N/A',
    'smtp_user' => $_CFG['smtp_user'] ?? 'N/A',
    'mail_service' => $_CFG['mail_service'] ?? 'N/A',
];

echo "<table>";
echo "<tr><th>設定項目</th><th>值</th><th>狀態</th></tr>";
echo "<tr><td>發送出貨通知信</td><td>{$_CFG['send_ship_email']}</td><td>" . ($_CFG['send_ship_email'] == '1' ? "<span class='success'>✓ 已啟用</span>" : "<span class='error'>✗ 未啟用</span>") . "</td></tr>";
echo "<tr><td>商店名稱</td><td>{$_CFG['shop_name']}</td><td><span class='success'>✓</span></td></tr>";
echo "<tr><td>郵件服務</td><td>" . ($_CFG['mail_service'] ?? 'N/A') . "</td><td><span class='success'>✓</span></td></tr>";
echo "<tr><td>SMTP 主機</td><td>" . ($_CFG['smtp_host'] ?? 'N/A') . "</td><td><span class='success'>✓</span></td></tr>";
echo "<tr><td>SMTP 埠號</td><td>" . ($_CFG['smtp_port'] ?? 'N/A') . "</td><td><span class='success'>✓</span></td></tr>";
echo "</table>";
echo "</div>";

// ============================================================================
// 步驟 2: 取得測試訂單
// ============================================================================
echo "<h2><div class='step'>步驟 2: 取得測試訂單</div></h2>";

// 從 URL 參數取得 order_id,如果沒有則使用最新的訂單
$test_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($test_order_id == 0) {
    // 取得最新的訂單
    $sql = "SELECT order_id FROM " . $ecs->table('order_info') . "
            WHERE email != '' AND email IS NOT NULL
            ORDER BY order_id DESC LIMIT 1";
    $test_order_id = $db->getOne($sql);
}

if (empty($test_order_id)) {
    echo "<div class='error'>✗ 錯誤: 找不到可用的測試訂單</div>";
    exit;
}

// 取得訂單詳細資料
$order = order_info($test_order_id);
$test_results['order'] = $order;

echo "<div class='info-box'>";
echo "<table>";
echo "<tr><th>訂單資訊</th><th>值</th></tr>";
echo "<tr><td>訂單編號</td><td>{$order['order_sn']}</td></tr>";
echo "<tr><td>訂單ID</td><td>{$order['order_id']}</td></tr>";
echo "<tr><td>收件人</td><td>{$order['consignee']}</td></tr>";
echo "<tr><td>Email</td><td>{$order['email']}</td></tr>";
echo "<tr><td>物流單號</td><td>" . ($order['invoice_no'] ?: '(無)') . "</td></tr>";
echo "<tr><td>發貨狀態</td><td>{$order['shipping_status']}</td></tr>";
echo "</table>";
echo "</div>";

// ============================================================================
// 步驟 3: 檢查郵件模板
// ============================================================================
echo "<h2><div class='step'>步驟 3: 檢查郵件模板</div></h2>";

$tpl = get_mail_template('deliver_notice');
$test_results['mail_template'] = $tpl;

echo "<div class='info-box'>";
if (!empty($tpl)) {
    echo "<p><span class='success'>✓ 郵件模板載入成功</span></p>";
    echo "<table>";
    echo "<tr><th>模板屬性</th><th>值</th></tr>";
    echo "<tr><td>模板ID</td><td>{$tpl['template_id']}</td></tr>";
    echo "<tr><td>模板代碼</td><td>{$tpl['template_code']}</td></tr>";
    echo "<tr><td>郵件主旨</td><td>{$tpl['template_subject']}</td></tr>";
    echo "<tr><td>是否HTML</td><td>" . ($tpl['is_html'] ? '是' : '否') . "</td></tr>";
    echo "<tr><td>模板類型</td><td>{$tpl['type']}</td></tr>";
    echo "</table>";

    echo "<h3>模板內容預覽:</h3>";
    echo "<pre>" . htmlspecialchars(substr($tpl['template_content'], 0, 500)) . "...</pre>";
} else {
    echo "<p><span class='error'>✗ 郵件模板載入失敗</span></p>";
}
echo "</div>";

// ============================================================================
// 步驟 4: 測試模板渲染
// ============================================================================
echo "<h2><div class='step'>步驟 4: 測試模板渲染</div></h2>";

$test_invoice_no = "TEST-" . date('YmdHis');

try {
    $smarty->assign('order', $order);
    $smarty->assign('send_time', local_date($_CFG['time_format']));
    $smarty->assign('shop_name', $_CFG['shop_name']);
    $smarty->assign('send_date', local_date($_CFG['date_format']));
    $smarty->assign('sent_date', local_date($_CFG['date_format']));
    $smarty->assign('confirm_url', ecs_remote_url() . 'receive.php?id=' . $order['order_id'] . '&con=' . rawurlencode($order['consignee']));
    $smarty->assign('send_msg_url', ecs_remote_url() . 'user.php?act=order_detail&order_id=' . $order['order_id']);

    // 渲染郵件內容
    $content = $smarty->fetch('str:' . $tpl['template_content']);
    $test_results['rendered_content'] = $content;

    echo "<div class='info-box'>";
    echo "<p><span class='success'>✓ 模板渲染成功</span></p>";
    echo "<h3>渲染後的郵件內容:</h3>";
    echo "<div style='border: 2px solid #ddd; padding: 15px; background: white;'>";
    echo $content;
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>✗ 模板渲染失敗: " . $e->getMessage() . "</div>";
    $test_results['render_error'] = $e->getMessage();
}

// ============================================================================
// 步驟 5: 測試實際發送 (選擇性)
// ============================================================================
echo "<h2><div class='step'>步驟 5: 測試實際發送</div></h2>";

echo "<div class='info-box'>";
echo "<p><span class='warning'>⚠️  注意: 實際發送郵件功能需要手動觸發</span></p>";

if (isset($_GET['send']) && $_GET['send'] == '1') {
    echo "<p><span class='warning'>正在發送測試郵件...</span></p>";

    try {
        // 建立 OrderOperateLib 實例
        $orderOperate = new OrderOperateLib();

        // 直接呼叫 sendShippingEmail 方法
        $result = $orderOperate->sendShippingEmail($order, $test_invoice_no);

        if ($result) {
            echo "<p><span class='success'>✓ 郵件發送成功!</span></p>";
            echo "<p>收件人: {$order['consignee']} &lt;{$order['email']}&gt;</p>";
            echo "<p>物流單號: {$test_invoice_no}</p>";
            $test_results['send_result'] = 'success';
        } else {
            echo "<p><span class='error'>✗ 郵件發送失敗</span></p>";
            echo "<p>可能原因:</p>";
            echo "<ul>";
            echo "<li>系統未啟用出貨通知信功能 (send_ship_email != '1')</li>";
            echo "<li>send_mail() 函式回傳 false</li>";
            echo "<li>SMTP 設定錯誤</li>";
            echo "</ul>";
            $test_results['send_result'] = 'failed';
        }
    } catch (Exception $e) {
        echo "<p><span class='error'>✗ 發生錯誤: " . htmlspecialchars($e->getMessage()) . "</span></p>";
        $test_results['send_result'] = 'error';
        $test_results['error_message'] = $e->getMessage();
    }
} else {
    echo "<p>如需測試實際發送,請點選以下連結:</p>";
    $send_url = $_SERVER['PHP_SELF'] . "?order_id=" . $test_order_id . "&send=1";
    echo "<p><a href='{$send_url}' style='display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>發送測試郵件</a></p>";
    echo "<p><small>測試物流單號: {$test_invoice_no}</small></p>";
}
echo "</div>";

// ============================================================================
// 測試結果總結
// ============================================================================
echo "<h2><div class='step'>測試結果總結</div></h2>";

echo "<div class='info-box'>";
echo "<h3>JSON 格式測試結果:</h3>";
echo "<pre>" . json_encode($test_results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

// ============================================================================
// 操作說明
// ============================================================================
echo "<h2>📖 使用說明</h2>";
echo "<div class='info-box'>";
echo "<ol>";
echo "<li>預設使用最新的訂單進行測試</li>";
echo "<li>可透過 URL 參數指定訂單: <code>?order_id=12345</code></li>";
echo "<li>點選「發送測試郵件」按鈕可實際發送測試郵件</li>";
echo "<li>測試郵件會發送到訂單中的 email 地址</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔧 相關檔案</h2>";
echo "<div class='info-box'>";
echo "<ul>";
echo "<li>出貨處理程式: <code>admin_Site168/includes/order/lib_delivery_ship.php</code></li>";
echo "<li>訂單操作類別: <code>admin_Site168/includes/order/lib_order_operate.php</code></li>";
echo "<li>發送郵件方法: <code>OrderOperateLib::sendShippingEmail()</code> (第 1870 行)</li>";
echo "<li>郵件函式: 系統核心的 <code>send_mail()</code> 函式</li>";
echo "<li>郵件模板: 資料庫 <code>ecs_mail_templates</code> 表,代碼為 <code>deliver_notice</code></li>";
echo "</ul>";
echo "<p><strong>測試方式:</strong> 本測試程式直接呼叫 <code>OrderOperateLib::sendShippingEmail()</code> 方法,確保測試結果與實際環境一致。</p>";
echo "</div>";

echo "</div>"; // container
echo "</body></html>";
?>
