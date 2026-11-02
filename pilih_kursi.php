<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$id_jadwal = isset($_GET['id_jadwal']) ? (int)$_GET['id_jadwal'] : 0;
if (!$id_jadwal) { header('Location: index.php'); exit; }

$jadwal = $conn->query("
    SELECT j.*, f.judul, f.poster, f.genre, f.durasi, f.rating_usia, m.nama_mall
    FROM jadwal j 
    JOIN film f ON j.id_film = f.id_film 
    LEFT JOIN mall m ON j.id_mall = m.id_mall
    WHERE j.id_jadwal = $id_jadwal
")->fetch_assoc();

if (!$jadwal) { header('Location: index.php'); exit; }

$kursiQuery = $conn->query("SELECT * FROM kursi WHERE id_jadwal = $id_jadwal 
                             ORDER BY SUBSTRING(kode_kursi,1,1), CAST(SUBSTRING(kode_kursi,2) AS UNSIGNED)");

$kursiPerBaris = [];
$totalKursi = 0; $totalTerisi = 0; $maxKursiPerBaris = 0;

while ($k = $kursiQuery->fetch_assoc()) {
    if (preg_match('/^([A-Za-z]+)(\d+)$/', $k['kode_kursi'], $m)) {
        $row = strtoupper($m[1]);
        if (!isset($kursiPerBaris[$row])) $kursiPerBaris[$row] = [];
        $kursiPerBaris[$row][] = $k;
        $totalKursi++;
        if ($k['status'] === 'dipesan') $totalTerisi++;
    }
}
ksort($kursiPerBaris);
foreach ($kursiPerBaris as $rowList) $maxKursiPerBaris = max($maxKursiPerBaris, count($rowList));
$aisleAfter = (int) ceil($maxKursiPerBaris / 2);
$kursiTersedia = $totalKursi - $totalTerisi;
$persenTerisi = $totalKursi > 0 ? round(($totalTerisi / $totalKursi) * 100) : 0;
$posterUrl = 'assets/img/' . ($jadwal['poster'] ?? 'default.jpg');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kursi - <?= htmlspecialchars($jadwal['judul']); ?> | WizCinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #060810;
            color: #F1F5F9;
            min-height: 100vh;
            padding-bottom: 130px;
            overflow-x: hidden;
        }

        /* ================= HERO ================= */
        .hero-film {
            position: relative;
            padding: 30px 20px 60px;
            overflow: hidden;
            isolation: isolate;
        }
        .hero-film::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url('<?= htmlspecialchars($posterUrl); ?>');
            background-size: cover;
            background-position: center 25%;
            filter: blur(50px) brightness(0.4) saturate(1.5);
            transform: scale(1.2);
            z-index: -2;
        }
        .hero-film::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(6,8,16,0.3) 0%, rgba(6,8,16,0.85) 60%, #060810 100%);
            z-index: -1;
        }
        .hero-inner { max-width: 900px; margin: 0 auto; position: relative; z-index: 1; }

        .back-nav {
            display: inline-flex; align-items: center; gap: 9px;
            color: #F1F5F9; text-decoration: none;
            font-weight: 600; font-size: 0.82rem;
            padding: 8px 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            margin-bottom: 20px;
            transition: 0.22s;
            backdrop-filter: blur(20px);
        }
        .back-nav:hover { color: #fff; background: rgba(255,255,255,0.15); transform: translateX(-3px); }

        .hero-card { display: flex; gap: 20px; align-items: flex-end; }
        .hero-poster {
            width: 110px; height: 155px;
            object-fit: cover; border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.15);
            box-shadow: 0 25px 50px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.05);
            flex-shrink: 0;
        }
        .hero-info { flex: 1; min-width: 0; padding-bottom: 4px; }
        .hero-title {
            font-size: 1.6rem; font-weight: 800;
            letter-spacing: -0.6px; margin-bottom: 10px;
            line-height: 1.15;
            text-shadow: 0 2px 12px rgba(0,0,0,0.5);
        }
        .hero-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .hero-tag {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            font-size: 0.7rem; font-weight: 700;
            color: #E2E8F0; letter-spacing: 0.3px;
        }
        /* 🔥 AMBER lebih kalem, bukan kuning neon */
        .hero-tag.warn {
            background: rgba(212, 168, 90, 0.12);
            border-color: rgba(212, 168, 90, 0.35);
            color: #E4C491;
        }
        .hero-schedule {
            display: flex; flex-wrap: wrap; gap: 6px 16px;
            font-size: 0.78rem; color: rgba(241,245,249,0.7);
            font-weight: 500;
        }
        .hero-schedule span { display: inline-flex; align-items: center; gap: 6px; }
        .hero-schedule i { color: #60A5FA; font-size: 0.72rem; }

        /* ================= FILL PROGRESS ================= */
        .fill-progress { max-width: 900px; margin: -30px auto 0; padding: 0 20px; position: relative; z-index: 2; }
        .fill-card {
            background: rgba(15,23,41,0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: 16px;
            padding: 16px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }
        .fill-info { flex-shrink: 0; }
        .fill-num { font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1; }
        .fill-num span { font-size: 0.9rem; color: #64748B; }
        .fill-label { font-size: 0.68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-top: 4px; }
        .fill-bar-wrap { flex: 1; }
        .fill-bar-bg { height: 8px; background: rgba(30,41,59,0.8); border-radius: 999px; overflow: hidden; }
        .fill-bar-fg {
            height: 100%;
            background: linear-gradient(90deg, #3B82F6, #60A5FA, #93C5FD);
            border-radius: 999px;
            transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 0 12px rgba(59,130,246,0.6);
        }
        .fill-bar-label { display: flex; justify-content: space-between; font-size: 0.7rem; color: #64748B; font-weight: 600; margin-top: 6px; }

        /* ================= MAIN ================= */
        .main-wrap { max-width: 900px; margin: 0 auto; padding: 30px 20px; }

        /* ================= SCREEN 3D ================= */
        .screen-stage {
            perspective: 1200px; perspective-origin: 50% 0%;
            margin: 10px auto 40px;
            max-width: 700px; text-align: center;
        }
        .screen-3d {
            transform: rotateX(28deg);
            transform-style: preserve-3d;
            position: relative; margin-bottom: 20px;
        }
        .screen-body {
            height: 60px;
            border-radius: 50% 50% 8px 8px / 80% 80% 8px 8px;
            background: linear-gradient(180deg,
                rgba(96,165,250,0.9) 0%,
                rgba(59,130,246,0.7) 30%,
                rgba(37,99,235,0.2) 70%,
                rgba(11,17,32,0) 100%);
            box-shadow: 
                0 -3px 30px rgba(96,165,250,0.9),
                0 -3px 60px rgba(59,130,246,0.6),
                0 0 100px rgba(59,130,246,0.4);
            position: relative; overflow: hidden;
        }
        .screen-body::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.5) 50%, transparent 100%);
            animation: screenShine 3s ease-in-out infinite;
        }
        @keyframes screenShine {
            0%,100% { opacity: 0.2; transform: translateX(-30%); }
            50% { opacity: 0.6; transform: translateX(30%); }
        }
        .screen-text { font-size: 0.65rem; letter-spacing: 8px; font-weight: 800; color: #475569; text-transform: uppercase; margin-top: 12px; }

        /* ================= SEAT ZONE ================= */
        .seat-zone {
            background: linear-gradient(180deg, rgba(15,23,41,0.5) 0%, rgba(11,17,32,0.9) 100%);
            border: 1px solid rgba(59,130,246,0.15);
            border-radius: 24px;
            padding: 34px 20px 40px;
            margin-bottom: 28px;
            position: relative; overflow-x: auto;
            box-shadow: inset 0 0 80px rgba(59,130,246,0.05);
        }
        .seat-zone::-webkit-scrollbar { height: 6px; }
        .seat-zone::-webkit-scrollbar-track { background: transparent; }
        .seat-zone::-webkit-scrollbar-thumb { background: #334155; border-radius: 6px; }

        .seat-area { display: flex; flex-direction: column; align-items: center; gap: 10px; min-width: max-content; margin: 0 auto; }
        .seat-row-wrap { display: flex; align-items: center; gap: 14px; }
        .row-label {
            width: 22px; text-align: center;
            font-size: 0.72rem; font-weight: 800; color: #475569;
            flex-shrink: 0; letter-spacing: 1px; user-select: none;
        }
        .seat-row { display: flex; gap: 7px; align-items: center; }
        .seat-aisle { width: 28px; height: 42px; flex-shrink: 0; position: relative; }
        .seat-aisle::before {
            content: ''; position: absolute;
            top: 50%; left: 50%; transform: translate(-50%,-50%);
            width: 1px; height: 60%;
            background: linear-gradient(180deg, transparent, #1E293B, transparent);
        }

        /* SEAT */
        .seat {
            width: 44px; height: 46px;
            border-radius: 12px 12px 5px 5px;
            font-weight: 800; font-size: 0.7rem;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            display: flex; align-items: center; justify-content: center;
            font-family: inherit; position: relative;
            animation: seatAppear 0.5s ease backwards;
        }
        @keyframes seatAppear {
            from { opacity: 0; transform: scale(0.5) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .seat-available {
            background: linear-gradient(180deg, #1A2540 0%, #0F1729 100%);
            color: #94A3B8; border-color: #334155;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 2px 4px rgba(0,0,0,0.3);
        }
        .seat-available:hover {
            background: linear-gradient(180deg, #3B82F6 0%, #2563EB 100%);
            color: #fff; border-color: #60A5FA;
            transform: translateY(-4px) scale(1.08);
            box-shadow: 0 8px 20px rgba(59,130,246,0.5), 0 0 0 3px rgba(59,130,246,0.2);
        }

        .seat-selected {
            background: linear-gradient(180deg, #60A5FA 0%, #3B82F6 50%, #2563EB 100%);
            color: #fff; border-color: #93C5FD;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 
                0 10px 24px rgba(59,130,246,0.6),
                0 0 0 3px rgba(96,165,250,0.3),
                inset 0 1px 0 rgba(255,255,255,0.3);
            animation: seatPulse 2s ease-in-out infinite;
        }
        /* 🔥 CHECKMARK PUTIH/BIRU — hilangkan hijau */
        .seat-selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; top: -6px; right: -6px;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #FFFFFF;
            color: #2563EB;
            font-size: 0.55rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(59,130,246,0.6);
            border: 2px solid #1E40AF;
            animation: checkPop 0.4s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes checkPop {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        @keyframes seatPulse {
            0%,100% { box-shadow: 0 10px 24px rgba(59,130,246,0.6), 0 0 0 3px rgba(96,165,250,0.3), inset 0 1px 0 rgba(255,255,255,0.3); }
            50% { box-shadow: 0 10px 30px rgba(59,130,246,0.8), 0 0 0 5px rgba(96,165,250,0.15), inset 0 1px 0 rgba(255,255,255,0.3); }
        }

        .seat-booked {
            background: #1E293B; color: #475569;
            cursor: not-allowed; opacity: 0.4;
            border-color: #1E293B; position: relative;
        }
        .seat-booked::before {
            content: ''; position: absolute;
            inset: 40% 20%; height: 1px;
            background: #475569; transform: rotate(-20deg);
        }

        /* 🔥 TOOLTIP amber kalem, bukan kuning neon */
        .seat[data-tip]::before {
            content: attr(data-tip);
            position: absolute;
            bottom: calc(100% + 10px); left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #E4C491 0%, #C9963F 100%);
            color: #2A1E0A;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.68rem; font-weight: 800;
            white-space: nowrap;
            opacity: 0; visibility: hidden;
            pointer-events: none;
            transition: 0.2s;
            box-shadow: 0 6px 16px rgba(201,150,63,0.4);
            z-index: 10; letter-spacing: 0.3px;
        }
        .seat[data-tip]::after {
            content: ''; position: absolute;
            bottom: calc(100% + 4px); left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #C9963F;
            opacity: 0; visibility: hidden;
            pointer-events: none; transition: 0.2s; z-index: 10;
        }
        .seat[data-tip]:hover::before,
        .seat[data-tip]:hover::after { opacity: 1; visibility: visible; }

        /* ================= LEGEND ================= */
        .legend {
            display: flex; justify-content: center; gap: 12px;
            flex-wrap: wrap;
            padding: 18px;
            background: rgba(15,23,41,0.6);
            backdrop-filter: blur(10px);
            border: 1px solid #1E293B;
            border-radius: 14px;
        }
        .legend-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px;
            background: rgba(11,17,32,0.6);
            border: 1px solid #1E293B;
            border-radius: 10px;
            font-size: 0.75rem; color: #94A3B8; font-weight: 700;
        }
        .legend-box {
            width: 20px; height: 20px;
            border-radius: 6px 6px 3px 3px;
            flex-shrink: 0; position: relative;
        }
        .legend-box.available {
            background: linear-gradient(180deg, #1A2540 0%, #0F1729 100%);
            border: 1.5px solid #334155;
        }
        .legend-box.selected {
            background: linear-gradient(180deg, #60A5FA, #2563EB);
            border: 1.5px solid #93C5FD;
            box-shadow: 0 0 8px rgba(59,130,246,0.6);
        }
        .legend-box.booked {
            background: #1E293B; border: 1.5px solid #1E293B; opacity: 0.4;
        }
        .legend-box.booked::after {
            content: ''; position: absolute;
            inset: 40% 20%; height: 1px;
            background: #475569; transform: rotate(-20deg);
        }

        /* ================= STICKY BAR ================= */
        .booking-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: rgba(6,8,16,0.92);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-top: 1px solid rgba(59,130,246,0.25);
            padding: 14px 20px; z-index: 100;
            box-shadow: 0 -20px 60px rgba(0,0,0,0.6), 0 -1px 0 rgba(59,130,246,0.1) inset;
        }
        .booking-bar-inner {
            max-width: 900px; margin: 0 auto;
            display: flex; align-items: center; gap: 20px;
        }

        .bar-chips-zone { flex: 1; min-width: 0; }
        .bar-chips-label {
            font-size: 0.65rem; color: #64748B;
            text-transform: uppercase; letter-spacing: 1.2px;
            font-weight: 800; margin-bottom: 6px;
        }
        .bar-chips {
            display: flex; gap: 6px; flex-wrap: nowrap;
            overflow-x: auto; padding-bottom: 2px;
        }
        .bar-chips::-webkit-scrollbar { height: 3px; }
        .bar-chips::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .bar-chips-empty { font-size: 0.82rem; color: #475569; font-weight: 600; font-style: italic; }
        .seat-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 9px;
            background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(37,99,235,0.15));
            border: 1px solid rgba(59,130,246,0.4);
            border-radius: 6px;
            font-size: 0.72rem; font-weight: 800;
            color: #60A5FA; flex-shrink: 0;
            animation: chipIn 0.3s ease;
        }
        @keyframes chipIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        .seat-chip i {
            font-size: 0.55rem; color: #93C5FD; cursor: pointer;
            padding: 2px; border-radius: 50%; transition: 0.2s;
        }
        .seat-chip i:hover { background: rgba(239,68,68,0.3); color: #FCA5A5; }

        .bar-actions { display: flex; align-items: center; gap: 18px; flex-shrink: 0; }
        .bar-total-box { text-align: right; }
        .bar-total-label {
            font-size: 0.62rem; color: #64748B;
            text-transform: uppercase; letter-spacing: 1px; font-weight: 800;
        }
        .bar-total-value {
            font-size: 1.25rem; font-weight: 800; color: #fff;
            line-height: 1; letter-spacing: -0.5px; margin-top: 2px;
        }
        /* 🔥 TOTAL amber kalem, bukan kuning neon */
        .bar-total-value.has-val {
            color: #E4C491;
            text-shadow: 0 0 20px rgba(228,196,145,0.35);
        }

        .bar-btn {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: #fff; border: none;
            border-radius: 13px;
            padding: 14px 28px;
            font-size: 0.92rem; font-weight: 800;
            font-family: inherit; cursor: pointer;
            transition: all 0.25s;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 
                0 8px 24px rgba(59,130,246,0.4),
                0 0 0 1px rgba(96,165,250,0.2) inset,
                0 1px 0 rgba(255,255,255,0.2) inset;
            letter-spacing: 0.3px; white-space: nowrap;
        }
        .bar-btn:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 
                0 12px 32px rgba(59,130,246,0.6),
                0 0 0 1px rgba(96,165,250,0.3) inset,
                0 1px 0 rgba(255,255,255,0.3) inset;
        }
        .bar-btn:active:not(:disabled) { transform: translateY(0) scale(0.99); }
        .bar-btn:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; filter: grayscale(0.5); }
        .bar-btn i { font-size: 0.85rem; }

        /* ================= EMPTY ================= */
        .empty-state { text-align: center; padding: 70px 20px; }
        .empty-state-icon {
            width: 90px; height: 90px;
            margin: 0 auto 20px;
            background: rgba(59,130,246,0.1);
            border: 2px dashed rgba(59,130,246,0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #3B82F6;
        }
        .empty-state h3 { font-size: 1.1rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .empty-state p { font-size: 0.85rem; color: #64748B; max-width: 280px; margin: 0 auto; line-height: 1.6; }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 700px) {
            .hero-film { padding: 20px 16px 55px; }
            .hero-poster { width: 80px; height: 115px; }
            .hero-title { font-size: 1.15rem; margin-bottom: 8px; }
            .hero-tag { font-size: 0.62rem; padding: 3px 8px; }
            .hero-schedule { font-size: 0.7rem; }
            .fill-progress { padding: 0 16px; }
            .fill-card { padding: 14px 16px; gap: 12px; }
            .fill-num { font-size: 1.15rem; }
            .main-wrap { padding: 24px 12px; }
            .seat-zone { padding: 26px 12px 30px; border-radius: 18px; }
            .seat { width: 36px; height: 38px; font-size: 0.62rem; }
            .seat-selected::after { width: 15px; height: 15px; font-size: 0.45rem; top: -5px; right: -5px; }
            .seat-row { gap: 5px; }
            .seat-aisle { width: 16px; height: 34px; }
            .row-label { width: 16px; font-size: 0.65rem; }
            .seat-row-wrap { gap: 8px; }
            .legend { padding: 14px 12px; gap: 8px; }
            .legend-item { padding: 6px 10px; font-size: 0.68rem; gap: 7px; }
            .legend-box { width: 16px; height: 16px; }
            .booking-bar { padding: 12px 14px; }
            .booking-bar-inner { flex-direction: column; align-items: stretch; gap: 10px; }
            .bar-chips-zone { order: 2; }
            .bar-actions { order: 1; justify-content: space-between; }
            .bar-total-box { text-align: left; }
            .bar-total-value { font-size: 1.1rem; }
            .bar-btn { padding: 12px 20px; font-size: 0.82rem; }
            .bar-btn span { display: none; }
            .bar-btn i { font-size: 1rem; }
            body { padding-bottom: 170px; }
        }
        @media (max-width: 420px) {
            .seat { width: 32px; height: 34px; font-size: 0.58rem; }
            .seat-row { gap: 4px; }
            .seat-aisle { width: 12px; height: 30px; }
            .seat-selected::after { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

    <section class="hero-film">
        <div class="hero-inner">
            <a href="detail_film.php?id=<?= $jadwal['id_film']; ?>" class="back-nav">
                <i class="fas fa-arrow-left"></i><span>Kembali</span>
            </a>
            <div class="hero-card">
                <img src="<?= htmlspecialchars($posterUrl); ?>" class="hero-poster"
                     onerror="this.src='assets/img/default.jpg'" alt="<?= htmlspecialchars($jadwal['judul']); ?>">
                <div class="hero-info">
                    <h1 class="hero-title"><?= htmlspecialchars($jadwal['judul']); ?></h1>
                    <div class="hero-tags">
                        <span class="hero-tag warn">
                            <i class="fas fa-tag"></i> Rp <?= number_format($jadwal['harga'], 0, ',', '.'); ?>
                        </span>
                        <?php if (!empty($jadwal['rating_usia'])): ?>
                            <span class="hero-tag"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($jadwal['rating_usia']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($jadwal['genre'])): ?>
                            <span class="hero-tag"><i class="fas fa-film"></i> <?= htmlspecialchars($jadwal['genre']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="hero-schedule">
                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($jadwal['nama_mall'] ?? 'WizCinema'); ?></span>
                        <span><i class="fas fa-couch"></i> Studio <?= htmlspecialchars($jadwal['studio']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($jadwal['tanggal'])); ?></span>
                        <span><i class="fas fa-clock"></i> <?= date('H:i', strtotime($jadwal['jam'])); ?> WIB</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="fill-progress">
        <div class="fill-card">
            <div class="fill-info">
                <div class="fill-num"><?= $kursiTersedia; ?><span>/<?= $totalKursi; ?></span></div>
                <div class="fill-label">Kursi Tersedia</div>
            </div>
            <div class="fill-bar-wrap">
                <div class="fill-bar-bg">
                    <div class="fill-bar-fg" style="width: <?= 100 - $persenTerisi; ?>%"></div>
                </div>
                <div class="fill-bar-label">
                    <span><?= $totalTerisi; ?> kursi terjual</span>
                    <span><?= $persenTerisi; ?>% terisi</span>
                </div>
            </div>
        </div>
    </div>

    <div class="main-wrap">

        <div class="screen-stage">
            <div class="screen-3d"><div class="screen-body"></div></div>
            <div class="screen-text">Layar Bioskop</div>
        </div>

        <form id="seatForm" method="POST" action="konfirmasi_pesanan.php">
            <input type="hidden" name="id_jadwal" value="<?= $id_jadwal; ?>">
            <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">

            <div class="seat-zone">
                <?php if (empty($kursiPerBaris)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-couch"></i></div>
                        <h3>Belum Ada Kursi</h3>
                        <p>Kursi untuk jadwal ini belum tersedia.</p>
                    </div>
                <?php else: ?>
                    <div class="seat-area">
                        <?php $rowIndex = 0; foreach ($kursiPerBaris as $rowLabel => $kursiList): $rowIndex++; ?>
                            <div class="seat-row-wrap">
                                <div class="row-label"><?= $rowLabel; ?></div>
                                <div class="seat-row">
                                    <?php $idx = 0; foreach ($kursiList as $k): $idx++;
                                        $available = $k['status'] === 'tersedia';
                                        $kursiNum = preg_replace('/^[A-Za-z]+/', '', $k['kode_kursi']);
                                        $delay = ($rowIndex * 0.04) + ($idx * 0.01);
                                    ?>
                                        <button type="button"
                                                class="seat <?= $available ? 'seat-available' : 'seat-booked'; ?>"
                                                data-seat="<?= htmlspecialchars($k['kode_kursi']); ?>"
                                                data-tip="<?= htmlspecialchars($k['kode_kursi']); ?><?= $available ? ' · Tersedia' : ' · Terisi'; ?>"
                                                style="animation-delay: <?= $delay; ?>s"
                                                <?= $available ? '' : 'disabled'; ?>>
                                            <?= htmlspecialchars($kursiNum); ?>
                                        </button>
                                        <?php if ($idx == $aisleAfter && count($kursiList) > $aisleAfter): ?>
                                            <div class="seat-aisle"></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row-label"><?= $rowLabel; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <div class="legend">
            <div class="legend-item"><span class="legend-box available"></span><span>Tersedia</span></div>
            <div class="legend-item"><span class="legend-box selected"></span><span>Dipilih</span></div>
            <div class="legend-item"><span class="legend-box booked"></span><span>Terisi</span></div>
        </div>

    </div>

    <div class="booking-bar">
        <div class="booking-bar-inner">
            <div class="bar-chips-zone">
                <div class="bar-chips-label">Kursi Dipilih</div>
                <div class="bar-chips" id="barChips">
                    <span class="bar-chips-empty">Belum ada kursi dipilih</span>
                </div>
            </div>
            <div class="bar-actions">
                <div class="bar-total-box">
                    <div class="bar-total-label">Total</div>
                    <div class="bar-total-value" id="barTotal">Rp 0</div>
                </div>
                <button type="submit" form="seatForm" class="bar-btn" id="confirmBtn" disabled>
                    <span>Lanjutkan</span><i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        const selected = new Set();
        const ticketPrice = <?= (int)$jadwal['harga']; ?>;
        const barChips = document.getElementById('barChips');
        const barTotal = document.getElementById('barTotal');
        const selectedSeatsInput = document.getElementById('selectedSeatsInput');
        const confirmBtn = document.getElementById('confirmBtn');

        function formatRupiah(num) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(num); }

        function renderChips() {
            if (selected.size === 0) {
                barChips.innerHTML = '<span class="bar-chips-empty">Belum ada kursi dipilih</span>';
                return;
            }
            const sorted = Array.from(selected).sort();
            barChips.innerHTML = sorted.map(code => `
                <span class="seat-chip">${code}<i class="fas fa-times" data-remove="${code}"></i></span>
            `).join('');
            barChips.querySelectorAll('[data-remove]').forEach(icon => {
                icon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const code = this.dataset.remove;
                    const seatBtn = document.querySelector(`.seat[data-seat="${code}"]`);
                    if (seatBtn) seatBtn.click();
                });
            });
        }

        function updateSummary() {
            const count = selected.size;
            if (count === 0) {
                barTotal.textContent = 'Rp 0';
                barTotal.classList.remove('has-val');
                confirmBtn.disabled = true;
            } else {
                barTotal.textContent = formatRupiah(count * ticketPrice);
                barTotal.classList.add('has-val');
                confirmBtn.disabled = false;
            }
            selectedSeatsInput.value = Array.from(selected).join(',');
            renderChips();
        }

        document.querySelectorAll('.seat:not(.seat-booked)').forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.disabled) return;
                const code = this.dataset.seat;
                if (selected.has(code)) {
                    selected.delete(code);
                    this.classList.remove('seat-selected');
                    this.classList.add('seat-available');
                } else {
                    selected.add(code);
                    this.classList.remove('seat-available');
                    this.classList.add('seat-selected');
                }
                updateSummary();
            });
        });

        document.getElementById('seatForm').addEventListener('submit', function (e) {
            if (selected.size === 0) { e.preventDefault(); alert('Pilih minimal 1 kursi!'); }
        });
        renderChips();
    </script>
</body>
</html>