<?php
// ============================================
// QR CODE AKSES WEBSITE WIZCINEMA
// ============================================

// ⬇️ GANTI DENGAN DOMAIN ANDA
$url = 'https://wizcinema.my.id/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR</title>
    <script src="qrcode.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #0B1120;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .qr-wrap {
            background: #fff;
            padding: 24px;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }
        #qrcode {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #qrcode img, #qrcode canvas { display: block; }
    </style>
</head>
<body>
    <div class="qr-wrap">
        <div id="qrcode"></div>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: <?= json_encode($url); ?>,
            width: 320,
            height: 320,
            colorDark: "#0B1120",
            colorLight: "#FFFFFF",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>