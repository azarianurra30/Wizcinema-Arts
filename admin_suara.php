<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_audio'])) {
    $_SESSION['volume'] = isset($_POST['volume']) ? (int)$_POST['volume'] : 70;
    header('Location: admin_suara.php?saved=1');
    exit;
}

$volume = isset($_SESSION['volume']) ? (int)$_SESSION['volume'] : 70;
$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Suara - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0B1120;
            color: #F1F5F9;
            display: flex;
            min-height: 100vh;
        }
        .main-content { flex: 1; padding: 32px 38px; min-width: 0; }

        .page-header { margin-bottom: 26px; }
        .page-header h4 {
            font-weight: 800;
            font-size: 1.6rem;
            color: #fff;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        .page-header h4 i {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
            box-shadow: 0 8px 20px rgba(59,130,246,0.35);
        }

        /* Alert */
        .alert-el {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: 0.88rem;
            font-weight: 500;
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.35);
            color: #6EE7B7;
        }
        .alert-el i { font-size: 1.1rem; }

        /* Card */
        .card-el {
            background: #111A2E;
            border: 1px solid #1E293B;
            border-radius: 18px;
            padding: 30px;
            max-width: 560px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header-el {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 18px;
            margin-bottom: 24px;
            border-bottom: 1px solid #1E293B;
        }
        .card-header-icon {
            width: 46px; height: 46px;
            border-radius: 13px;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(59,130,246,0.35);
        }
        .card-header-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .card-header-sub {
            font-size: 0.76rem;
            color: #64748B;
            margin-top: 3px;
        }

        /* Volume Display */
        .volume-display {
            text-align: center;
            padding: 24px 20px;
            background: #0F1729;
            border: 1px solid #1E293B;
            border-radius: 14px;
            margin-bottom: 22px;
        }
        .volume-display .icon {
            font-size: 1.6rem;
            color: #60A5FA;
            margin-bottom: 10px;
            display: inline-block;
        }
        .volume-display .value {
            font-size: 3rem;
            font-weight: 800;
            color: #60A5FA;
            line-height: 1;
            letter-spacing: -1px;
        }
        .volume-display .value span {
            font-size: 1.2rem;
            color: #475569;
            font-weight: 700;
        }

        /* Slider */
        .form-range {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: #0B1120;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
            border: 1px solid #1E293B;
            margin-bottom: 10px;
        }
        .form-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            cursor: pointer;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2), 0 4px 12px rgba(59,130,246,0.5);
        }
        .form-range::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }
        .form-range::-moz-range-thumb {
            width: 22px; height: 22px;
            border-radius: 50%; border: none;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            cursor: pointer;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2);
        }

        .slider-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: #64748B;
            font-weight: 600;
            padding: 0 4px;
            margin-bottom: 24px;
        }

        /* Button */
        .btn-submit-el {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 11px;
            font-weight: 700;
            font-size: 0.9rem;
            font-family: inherit;
            color: #fff;
            cursor: pointer;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
        }
        .btn-submit-el:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(59,130,246,0.45);
        }

        @media (max-width: 900px) {
            body { flex-direction: column; }
            .main-content { padding: 22px; }
            .card-el { padding: 22px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <h4><i class="fas fa-volume-up"></i> Kelola Suara</h4>
        </div>

        <?php if($saved): ?>
            <div class="alert-el">
                <i class="fas fa-check-circle"></i>
                Pengaturan suara berhasil disimpan!
            </div>
        <?php endif; ?>

        <div class="card-el">
            <div class="card-header-el">
                <div class="card-header-icon"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <div class="card-header-title">Kontrol Volume</div>
                    <div class="card-header-sub">Atur tingkat volume sistem</div>
                </div>
            </div>

            <form method="POST">
                <div class="volume-display">
                    <i class="fas fa-volume-up icon" id="volIcon"></i>
                    <div class="value"><span id="volLabel"><?= $volume; ?></span><span>%</span></div>
                </div>

                <input type="range" name="volume" class="form-range" min="0" max="100" 
                       value="<?= $volume; ?>" id="volumeSlider"
                       oninput="updateVolume(this.value)">

                <div class="slider-labels">
                    <span>0%</span>
                    <span>50%</span>
                    <span>100%</span>
                </div>

                <button type="submit" name="save_audio" class="btn-submit-el">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
            </form>
        </div>
    </div>

    <script>
        function updateVolume(val) {
            const v = parseInt(val);
            document.getElementById('volLabel').textContent = v;

            const icon = document.getElementById('volIcon');
            if (v === 0) icon.className = 'fas fa-volume-mute icon';
            else if (v < 40) icon.className = 'fas fa-volume-down icon';
            else if (v < 75) icon.className = 'fas fa-volume-up icon';
            else icon.className = 'fas fa-bullhorn icon';
        }

        // Init on load
        updateVolume(<?= $volume; ?>);
    </script>
</body>
</html>