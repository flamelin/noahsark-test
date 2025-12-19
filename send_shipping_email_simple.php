<?php
define('IN_ECS', true);

// 設定字元編碼
header('Content-Type: text/html; charset=utf-8');

// 載入前台核心檔案
require(dirname(__FILE__) . '/../includes/init.php');

// 載入訂單相關函式庫
require(dirname(__FILE__) . '/../includes/lib_order.php');

// 載入 OrderOperate 類別
require(dirname(__FILE__) . '/../admin_Site168/includes/order/lib_order_operate.php');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>測試 出貨通知 Email 發送工具</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #f7971e;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(247, 151, 30, 0.5);
        }
        .btn:active {
            transform: translateY(0);
        }
        .result {
            margin-top: 25px;
            padding: 20px;
            border-radius: 8px;
            animation: slideIn 0.3s ease-out;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            margin-bottom: 20px;
        }
        .result h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        .result p {
            margin: 8px 0;
            line-height: 1.6;
        }
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #f7971e;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>測試 出貨通知 Email 發送工具</h1>

        <?php
        // 判斷是否有提交表單
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_sn'])) {
            $order_sn = trim($_POST['order_sn']);

            echo "<div class='result'>";

            // 檢查訂單編號是否為空
            if (empty($order_sn)) {
                echo "<div class='error'>";
                echo "<h3>❌ 錯誤</h3>";
                echo "<p>請輸入訂單編號</p>";
                echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 返回</a>";
                echo "</div>";
                echo "</div>";
            } else {
                // 查詢訂單
                $sql = "SELECT order_id FROM " . $ecs->table('order_info') . "
                        WHERE order_sn = '" . addslashes($order_sn) . "'";
                $order_id = $db->getOne($sql);

                if (empty($order_id)) {
                    echo "<div class='error'>";
                    echo "<h3>❌ 找不到訂單</h3>";
                    echo "<p>訂單編號：<strong>{$order_sn}</strong></p>";
                    echo "<p>請確認訂單編號是否正確</p>";
                    echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 返回</a>";
                    echo "</div>";
                    echo "</div>";
                } else {
                    // 取得訂單詳細資料
                    $order = order_info($order_id);

                    // 顯示訂單資訊
                    echo "<div class='info'>";
                    echo "<h3>📦 訂單資訊</h3>";
                    echo "<p>訂單編號：<strong>{$order['order_sn']}</strong></p>";
                    echo "<p>收件人：{$order['consignee']}</p>";
                    echo "<p>Email：{$order['email']}</p>";
                    echo "</div>";

                    // 檢查 Email 是否存在
                    if (empty($order['email'])) {
                        echo "<div class='error'>";
                        echo "<h3>❌ 無法發送</h3>";
                        echo "<p>此訂單沒有 Email 地址</p>";
                        echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 返回</a>";
                        echo "</div>";
                        echo "</div>";
                    } else {
                        // 發送郵件
                        try {
                            // 建立 OrderOperateLib 實例
                            $orderOperate = new OrderOperateLib();

                            // 產生測試物流單號
                            $invoice_no = "TEST-" . date('YmdHis');

                            // 發送出貨通知信
                            $result = $orderOperate->sendShippingEmail($order, $invoice_no);

                            if ($result) {
                                echo "<div class='success'>";
                                echo "<h3>✅ 郵件發送成功！</h3>";
                                echo "<p>收件人：{$order['consignee']} &lt;{$order['email']}&gt;</p>";
                                echo "<p>物流單號：{$invoice_no}</p>";
                                echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 發送下一封</a>";
                                echo "</div><br>";
                            } else {
                                echo "<div class='error'>";
                                echo "<h3>❌ 郵件發送失敗</h3>";
                                echo "<p>可能原因：</p>";
                                echo "<ul style='margin-left: 20px; margin-top: 10px;'>";
                                echo "<li>系統未啟用出貨通知信功能</li>";
                                echo "<li>SMTP 設定錯誤</li>";
                                echo "<li>郵件伺服器連線失敗</li>";
                                echo "</ul>";
                                echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 返回</a>";
                                echo "</div>";
                            }
                        } catch (Exception $e) {
                            echo "<div class='error'>";
                            echo "<h3>❌ 發生錯誤</h3>";
                            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "<a href='{$_SERVER['PHP_SELF']}' class='back-link'>← 返回</a>";
                            echo "</div>";
                        }
                        echo "</div>";
                    }
                }
            }
        } else {
            // 顯示表單
        ?>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <div class="form-group">
                <label for="order_sn">訂單編號</label>
                <input type="text" id="order_sn" name="order_sn" placeholder="請輸入訂單編號" required autofocus>
                <p class="hint">例如：od2025081474231</p>
            </div>
            <button type="submit" class="btn">發送郵件</button>
        </form>
        <?php } ?>
    </div>
</body>
</html>
