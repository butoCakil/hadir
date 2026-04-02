<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header("Location: ../app/login.php");
    exit();
}

include "../config/koneksi.php";

$numbers = [];
$jumlahNumber = 0;
$result = $conn->query("SELECT nohp FROM datasiswa WHERE nohp IS NOT NULL AND nohp != ''");
while ($row = $result->fetch_assoc()) {
    $numbers[] = $row['nohp'];
    $jumlahNumber++;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Broadcast Pesan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f9f9f9;
        }

        textarea {
            width: 100%;
            height: 120px;
            padding: 8px;
        }

        #controls {
            margin: 10px 0;
        }

        button {
            padding: 8px 16px;
            margin-right: 5px;
            cursor: pointer;
        }

        #log {
            border: 1px solid #ccc;
            padding: 10px;
            height: 300px;
            overflow-y: scroll;
            background: #fff;
        }

        #progress-container {
            width: 100%;
            background: #ddd;
            border-radius: 10px;
            margin-top: 10px;
            height: 22px;
        }

        #progress-bar {
            width: 0;
            height: 100%;
            background: #4caf50;
            border-radius: 10px;
            text-align: center;
            color: white;
            font-size: 12px;
            transition: width 0.3s;
        }

        .ok {
            color: green;
        }

        .fail {
            color: red;
        }

        input[type="url"],
        input[type="number"] {
            padding: 5px;
            width: 100%;
        }
    </style>
</head>

<body>

    <h2>📢 Broadcast Pesan</h2>

    <form id="broadcastForm" enctype="multipart/form-data">
        <label>Pesan:</label><br>
        <textarea name="response" required placeholder="Tulis pesan broadcast di sini..."></textarea><br><br>

        <label>Upload File (opsional):</label><br>
        <input type="file" name="file"><br><br>

        <label>Atau masukkan URL file (opsional):</label><br>
        <input type="url" name="file_url"
            placeholder="https://hadir.xxxxxxx.com/data/Format_Laporan_PKL-Kelas_XI.pdf"><br><br>

        <label>Delay antar pesan (detik):</label><br>
        <input type="number" id="delay" value="3" min="0" step="1" style="width:80px;"> <small>(atur agar tidak kena
            limit API)</small><br><br>

        <div id="controls">
            <button type="submit" id="startBtn">Mulai Broadcast</button>
            <button type="button" id="pauseBtn" disabled>⏸️ Pause</button>
            <button type="button" id="resumeBtn" disabled>▶️ Resume</button>
        </div>
    </form>

    <div id="progress-info" style="margin-bottom:5px; font-weight:bold; display:none;">
        0 dari <?php echo $jumlahNumber; ?> pesan terkirim (0%)
    </div>

    <div id="progress-container" style="display:none;">
        <div id="progress-bar">0%</div>
    </div>

    <h3>Log Pengiriman:</h3>
    <div id="log"></div>

    <script>
        const numbers = <?php echo json_encode($numbers); ?>;
        const logDiv = document.getElementById('log');
        const progressBar = document.getElementById('progress-bar');
        const progressContainer = document.getElementById('progress-container');
        const pauseBtn = document.getElementById('pauseBtn');
        const resumeBtn = document.getElementById('resumeBtn');
        const startBtn = document.getElementById('startBtn');

        let paused = false;

        function logMessage(msg, cls = '') {
            logDiv.innerHTML += `<div class="${cls}">${msg}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function updateProgress(done, total) {
            const percent = Math.round((done / total) * 100);
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            document.getElementById('progress-info').textContent =
                `${done} dari ${total} pesan terkirim (${percent}%)`;
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        pauseBtn.addEventListener('click', () => {
            paused = true;
            pauseBtn.disabled = true;
            resumeBtn.disabled = false;
            logMessage('⏸️ Pengiriman dijeda...');
        });

        resumeBtn.addEventListener('click', () => {
            paused = false;
            resumeBtn.disabled = true;
            pauseBtn.disabled = false;
            logMessage('▶️ Lanjut mengirim...');
        });

        document.getElementById('broadcastForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (numbers.length === 0) return alert('Tidak ada nomor penerima.');

            startBtn.disabled = true;
            pauseBtn.disabled = false;
            progressContainer.style.display = 'block';

            const formData = new FormData(e.target);
            const delay = parseInt(document.getElementById('delay').value || 0) * 1000;
            let sent = 0;

            progressContainer.style.display = 'block';
            document.getElementById('progress-info').style.display = 'block';

            for (const number of numbers) {
                while (paused) await sleep(500);
                formData.set('number', number);

                try {
                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    logMessage(`${(sent + 1)}✅ Terkirim ke ${result.number}`, 'ok');
                } catch (err) {
                    logMessage(`❌ Gagal kirim ke ${number}`, 'fail');
                }

                sent++;
                updateProgress(sent, numbers.length);
                if (sent < numbers.length && delay > 0) await sleep(delay);
            }

            logMessage('<b>✅ Semua pesan telah dikirim!</b>');
            pauseBtn.disabled = true;
            resumeBtn.disabled = true;
        });
    </script>

</body>

</html>