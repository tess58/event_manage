<?php
require_once 'includes/config.php';
require_login();
if (!is_role('organizer')) {
    header('Location: login.php');
    exit;
}
$pageTitle = 'Organizer QR Check-In | Event Ethiopia';
$organizerId = (int) $_SESSION['user_id'];
$message = '';
$error = '';
$qrColumn = get_booking_code_column($mysqli);
ensure_booking_qr_schema($mysqli);
$selectedEventId = (int) ($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$events = $mysqli->query("SELECT id,title FROM events WHERE organizer_id=" . (int) $organizerId . " ORDER BY date DESC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketCode = trim($_POST['ticket_code'] ?? '');
    if ($ticketCode === '') {
        $error = 'Please enter or scan a ticket code.';
    } elseif ($selectedEventId <= 0) {
        $error = 'Select an event before scanning.';
    } else {
        $rawPayload = $ticketCode;
        if (strpos($ticketCode, '{') === 0) {
            [$isValidPayload, $payloadError, $payloadData] = validate_qr_payload($ticketCode);
            if (!$isValidPayload) {
                $error = 'Invalid QR data.';
            } else {
                $rawPayload = (string) $payloadData['ticket_number'];
            }
        }

        if ($error === '') {
            $findStmt = $mysqli->prepare("SELECT b.id,b.user_id,b.event_id,b.status,b.check_in_status,b.$qrColumn ticket_code,b.qr_code_data FROM bookings b JOIN events e ON e.id=b.event_id WHERE (b.$qrColumn=? OR b.qr_code_data=?) AND e.organizer_id=? LIMIT 1");
            $findStmt->bind_param('ssi', $rawPayload, $ticketCode, $organizerId);
            $findStmt->execute();
            $booking = $findStmt->get_result()->fetch_assoc();
            $findStmt->close();
            if (!$booking) {
                $error = 'Ticket Not Found';
            } elseif ((int) $booking['event_id'] !== $selectedEventId) {
                $error = 'Invalid Ticket for selected event';
            } elseif ((string) $booking['status'] === 'cancelled') {
                $error = 'Booking Cancelled';
            } elseif ((string) $booking['status'] !== 'confirmed') {
                $error = 'Invalid Ticket';
            } else {
                $eventDateRow = $mysqli->query("SELECT date FROM events WHERE id=" . (int) $selectedEventId . " AND organizer_id=" . (int) $organizerId . " LIMIT 1")->fetch_assoc();
                if (!$eventDateRow) {
                    $error = 'Invalid Ticket';
                } elseif (strtotime((string) $eventDateRow['date']) < strtotime(date('Y-m-d'))) {
                    $error = 'Expired event';
                } elseif ((string) $booking['check_in_status'] === 'checked_in') {
                    $error = 'Already Used';
                } else {
                    $updateStmt = $mysqli->prepare("UPDATE bookings b JOIN events e ON e.id=b.event_id SET b.check_in_status='checked_in',b.checked_in_at=NOW(),e.attendance_count=e.attendance_count+1 WHERE b.id=? AND e.organizer_id=?");
                    $updateStmt->bind_param('ii', $booking['id'], $organizerId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $message = 'Check-in successful.';
                }
            }
        }
    }
}

$rowsStmt = $mysqli->prepare("SELECT u.name user_name, e.title event_title, b.$qrColumn ticket_code, b.checked_in_at FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id WHERE e.organizer_id=? AND b.check_in_status='checked_in' ORDER BY b.checked_in_at DESC LIMIT 100");
$rowsStmt->bind_param('i', $organizerId);
$rowsStmt->execute();
$rows = $rowsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rowsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= sanitize($pageTitle) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <main class="page-content wrapper">
        <section class="heading-bar">
            <h2>QR Check-In</h2><a href="organizer-dashboard.php" class="button button-alt">Back</a>
        </section>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
        <div class="grid-2">
            <div class="form-card table-card">
                <h3>Scan / Enter Ticket</h3>
                <form method="post" class="form-grid"><select name="event_id" id="event_id" required>
                        <option value="">Select event</option><?php foreach ($events as $event): ?><option value="<?= (int) $event['id'] ?>" <?= $selectedEventId === (int) $event['id'] ? 'selected' : '' ?>><?= sanitize($event['title']) ?></option><?php endforeach; ?>
                    </select><input type="text" id="ticket_code" name="ticket_code" placeholder="QR payload or ticket number" required><button class="button" type="submit">Mark Attendance</button></form>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;"><button type="button" id="start-camera" class="button button-alt">Start Camera Scan</button><button type="button" id="stop-camera" class="button button-alt">Stop Camera</button></div>
                <p id="camera-status" style="margin:10px 0 0;color:var(--text-secondary);font-size:.9rem;">Camera scanner is idle. Use HTTPS or localhost for camera access.</p>
                <div class="qr-reader-wrap" id="qr-reader" style="margin-top:14px;min-height:200px;max-width:100%;"></div>
            </div>
            <div class="dashboard-card table-card">
                <h3>Checked-In Attendees</h3>
                <div class="table-scroll">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Event</th>
                                <th>Code</th>
                                <th>Check-In Time</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($rows as $row): ?><tr>
                                    <td><?= sanitize($row['user_name']) ?></td>
                                    <td><?= sanitize($row['event_title']) ?></td>
                                    <td><?= sanitize($row['ticket_code']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($row['checked_in_at'])) ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script>
        (function() {
            let Html5Qrcode = null;
            const target = document.getElementById('ticket_code');
            const startBtn = document.getElementById('start-camera');
            const stopBtn = document.getElementById('stop-camera');
            const cameraStatus = document.getElementById('camera-status');
            const checkinForm = document.querySelector('.form-card form.form-grid');
            let qr = null;
            let cameraRunning = false;
            let lastDecodeAt = 0;
            let submitTimer = null;
            let libraryLoading = false;

            function qrBoxSize() {
                return Math.min(280, Math.max(160, window.innerWidth - 56));
            }

            function scheduleAutoSubmit() {
                if (!checkinForm || !target || !target.value.trim()) return;
                if (submitTimer) clearTimeout(submitTimer);
                submitTimer = setTimeout(function() {
                    if (typeof checkinForm.requestSubmit === 'function') {
                        checkinForm.requestSubmit();
                    } else {
                        checkinForm.submit();
                    }
                }, 300);
            }

            async function loadQRLibrary() {
                if (Html5Qrcode) return true;
                if (libraryLoading) return false;
                
                libraryLoading = true;
                try {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/html5-qrcode@2.2.5';
                    script.async = true;
                    script.timeout = 8000;
                    
                    await new Promise(function(resolve, reject) {
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                    
                    Html5Qrcode = window.Html5Qrcode;
                    libraryLoading = false;
                    return true;
                } catch (err) {
                    libraryLoading = false;
                    cameraStatus.textContent = 'Failed to load QR library. Use manual ticket entry.';
                    return false;
                }
            }

            async function getPreferredCameraId() {
                if (!Html5Qrcode || !Html5Qrcode.getCameras) {
                    return null;
                }
                try {
                    const cameras = await Html5Qrcode.getCameras();
                    if (!cameras || cameras.length === 0) {
                        return null;
                    }
                    const rearCamera = cameras.find(function(c) {
                        return /back|rear|environment|wide|0|main/i.test(c.label || '');
                    });
                    return rearCamera ? rearCamera.id : cameras[0].id;
                } catch (err) {
                    return null;
                }
            }

            async function startCamera() {
                const eventSelect = document.getElementById('event_id');
                if (eventSelect && !eventSelect.value) {
                    cameraStatus.textContent = 'Select an event before starting camera scan.';
                    return;
                }
                if (cameraRunning) {
                    return;
                }

                cameraStatus.textContent = 'Loading camera scanner...';
                
                const libraryLoaded = await loadQRLibrary();
                if (!libraryLoaded) {
                    return;
                }

                try {
                    if (!qr) {
                        qr = new Html5Qrcode('qr-reader');
                    }

                    cameraStatus.textContent = 'Requesting camera access...';
                    
                    const scanConfig = {
                        fps: 15,
                        qrbox: qrBoxSize(),
                        aspectRatio: 1.0
                    };
                    
                    const onDecoded = function(decodedText) {
                        var now = Date.now();
                        if (now - lastDecodeAt < 800) return;
                        lastDecodeAt = now;
                        if (target) target.value = decodedText;
                        cameraStatus.textContent = 'QR detected! Processing...';
                        scheduleAutoSubmit();
                    };
                    
                    const onError = function() {
                        // Silent error handling to avoid spam
                    };
                    
                    const preferredCameraId = await getPreferredCameraId();
                    
                    let cameraSource;
                    if (preferredCameraId) {
                        cameraSource = {
                            deviceId: { exact: preferredCameraId }
                        };
                    } else {
                        cameraSource = {
                            facingMode: { exact: 'environment' }
                        };
                    }
                    
                    await qr.start(cameraSource, scanConfig, onDecoded, onError);
                    cameraRunning = true;
                    cameraStatus.textContent = '📷 Rear camera active. Point at QR code to scan.';
                } catch (err) {
                    let errorMsg = 'Unable to access rear camera.';
                    if (err && err.message) {
                        if (err.message.includes('NotAllowedError')) {
                            errorMsg = 'Camera permission denied. Check browser settings.';
                        } else if (err.message.includes('NotFoundError')) {
                            errorMsg = 'No camera found on device.';
                        } else if (err.message.includes('environment')) {
                            errorMsg = 'Rear camera unavailable. Trying front camera...';
                            try {
                                const scanConfig = {
                                    fps: 15,
                                    qrbox: qrBoxSize(),
                                    aspectRatio: 1.0
                                };
                                const onDecoded = function(decodedText) {
                                    var now = Date.now();
                                    if (now - lastDecodeAt < 800) return;
                                    lastDecodeAt = now;
                                    if (target) target.value = decodedText;
                                    scheduleAutoSubmit();
                                };
                                await qr.start({ facingMode: 'user' }, scanConfig, onDecoded, function() {});
                                cameraRunning = true;
                                cameraStatus.textContent = '📷 Front camera active. Point at QR code to scan.';
                                return;
                            } catch (e) {
                                errorMsg = 'No camera available.';
                            }
                        }
                    }
                    cameraStatus.textContent = errorMsg + ' Use manual ticket entry.';
                }
            }

            async function stopCamera() {
                if (!cameraRunning || !qr) {
                    cameraStatus.textContent = 'Camera scanner is idle.';
                    return;
                }
                try {
                    await qr.stop();
                    cameraRunning = false;
                    cameraStatus.textContent = 'Camera stopped.';
                } catch (err) {
                    cameraStatus.textContent = 'Camera stopped.';
                    cameraRunning = false;
                }
            }

            if (startBtn) startBtn.addEventListener('click', startCamera);
            if (stopBtn) stopBtn.addEventListener('click', stopCamera);
        })();
    </script>
</body>

</html>
