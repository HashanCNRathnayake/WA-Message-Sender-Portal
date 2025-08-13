<?php
$apiUrl = "https://graph.facebook.com/v23.0/" . $_ENV['BUSINESS_ACCOUNT_ID'] . "/message_templates";

// Fetch & Update DB
$responseData = null;
if (isset($_GET['refresh'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $_ENV['WHATSAPP_TOKEN']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);

    if (isset($responseData['data'])) {
        $incomingIds = [];

        foreach ($responseData['data'] as $template) {
            $id = $conn->real_escape_string($template['id']);
            $name = $conn->real_escape_string($template['name']);
            $status = $conn->real_escape_string($template['status']);
            $components = json_encode($template['components']);
            $language = $conn->real_escape_string($template['language']);

            $incomingIds[] = "'$id'"; // Collect IDs as SQL-safe strings

            // Insert or Update
            $stmt = $conn->prepare("
                INSERT INTO whatsapp_templates (id, name, status, components, language)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    status = VALUES(status), 
                    components = VALUES(components),
                    language = VALUES(language)
            ");
            $stmt->bind_param("sssss", $id, $name, $status, $components, $language);
            $stmt->execute();
        }

        $stmt->close();

        // Delete templates that are no longer in the API response
        if (!empty($incomingIds)) {
            $idList = implode(",", $incomingIds);
            $conn->query("DELETE FROM whatsapp_templates WHERE id NOT IN ($idList)");
        }
    }
}

// Get templates from DB for dropdown
$dbTemplates = [];
// $result = $conn->query("SELECT * FROM whatsapp_templates ORDER BY name");
$result = $conn->query("SELECT * FROM whatsapp_templates WHERE name != 'hello_world' ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $dbTemplates[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>WhatsApp Template Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .component-block {
            background-color: #fff;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }

        .component-type {
            /* display: none; */
            color: #0d6efd;
            font-size: 10px;
        }
    </style>
</head>

<body class="bg-light">
    <div class="mb-3">
        <div class="">
            <div class="">

                <div class="">
                    <div class="text-end">
                        <a href="?refresh=1" class="btn btn-sm text-primary mb-2 fw-bold">
                            <i class="fa-solid fa-rotate text-primary me-1"></i>Sync Templates
                        </a>
                    </div>


                    <div class="d-flex flex-row">
                        <div class="flex-grow-1 me-2">

                            <label for="message" class="form-label">Message Template</label>
                            <select name="message" id="message" required class="form-select  " onchange="showDetails()">
                                <option value="" disabled selected>-- Select</option>
                                <?php foreach ($dbTemplates as $tpl): ?>
                                    <option value="<?= htmlspecialchars($tpl['name']) ?>"><?= htmlspecialchars($tpl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>


                        </div>
                        <input type="hidden" name="language" id="language">
                    </div>


                </div>
            </div>

        </div>
    </div>
    <script>
        const templateData = <?php echo json_encode($dbTemplates); ?>;

        function showDetails() {
            const selected = document.getElementById('message').value;

            const details = templateData.find(t => t.name === selected);
            const langSelect = document.getElementById('language');
            const langSelectDiv = document.getElementById('language2');
            const statusDiv = document.getElementById('status');
            const componentsDiv = document.getElementById('components');
            langSelectDiv.innerHTML = '';
            statusDiv.innerHTML = '';
            componentsDiv.innerHTML = '';
            if (details) {
                const components = JSON.parse(details.components);

                langSelect.value = details.language || "en_US";

                langSelectDiv.innerHTML = `<span class="badge bg-primary">Language: ${details.language}</span>`;

                let statusColor = 'bg-secondary';

                if (details.status === 'APPROVED') {
                    statusColor = 'bg-success';
                } else if (details.status === 'REJECTED') {
                    statusColor = 'bg-danger';
                } else if (details.status === 'IN_REVIEW' || details.status === 'PENDING' || details.status === 'PAUSED') {
                    statusColor = 'bg-warning text-dark';
                }

                statusDiv.innerHTML = `<span class="badge ${statusColor}">Status: ${details.status}</span>`;


                components.forEach(comp => {
                    const block = document.createElement('div');
                    block.className = 'component-block';
                    block.innerHTML = `
                        <div class="component-type">${comp.type}</div>
                        <div>${(comp.text ?? '[No text]').replace(/\n/g, '<br>')}</div>
                    `;
                    componentsDiv.appendChild(block);
                });
            }

            // console.log("User selected option:", selected);
            // Get DOM elements
            const tempVariableSec = document.getElementById('tempVariableSec');
            const courseName = document.getElementById('courseName');
            const pillarEmail = document.getElementById('pillarEmail');
            const tempDate = document.getElementById('tempDate');
            const hour = document.getElementById('hour');
            const minute = document.getElementById('minute');
            const ampm = document.getElementById('ampm');
            const tempLocation = document.getElementById('tempLocation');

            const Original_Date = document.getElementById('Original_Date');
            const ODT_hour = document.getElementById('ODT_hour');
            const ODT_minute = document.getElementById('ODT_minute');
            const ODT_ampm = document.getElementById('ODT_ampm');

            const New_Date = document.getElementById('New_Date');
            const NDT_hour = document.getElementById('NDT_hour');
            const NDT_minute = document.getElementById('NDT_minute');
            const NDT_ampm = document.getElementById('NDT_ampm');

            const name = document.getElementById('name');
            const platformURL = document.getElementById('platformURL');
            const launchDate = document.getElementById('launchDate');

            const portalName = document.getElementById('portalName');
            const moduleName = document.getElementById('moduleName');
            const deadlineDate = document.getElementById('deadlineDate');

            const SUS_hour = document.getElementById('SUS_hour');
            const SUS_minute = document.getElementById('SUS_minute');
            const SUS_ampm = document.getElementById('SUS_ampm');

            const SUE_hour = document.getElementById('SUE_hour');
            const SUE_minute = document.getElementById('SUE_minute');
            const SUE_ampm = document.getElementById('SUE_ampm');






            // Grouped sets
            const all = [courseName, pillarEmail, tempDate, hour, minute, ampm, tempLocation, Original_Date, ODT_hour, ODT_minute, ODT_ampm, New_Date, NDT_hour, NDT_minute, NDT_ampm, name, platformURL, launchDate, portalName, moduleName, deadlineDate, SUS_hour, SUS_minute, SUS_ampm, SUE_hour, SUE_minute, SUE_ampm];



            const set1 = [courseName, pillarEmail, tempDate, hour, minute, ampm, tempLocation];

            const set1_1 = [pillarEmail];
            const set1_2 = [tempDate, hour, minute, ampm, tempLocation];



            const set2 = [Original_Date, ODT_hour, ODT_minute, ODT_ampm, New_Date, NDT_hour, NDT_minute, NDT_ampm];

            const set3 = [name, platformURL, launchDate, moduleName, deadlineDate, portalName, SUS_hour, SUS_minute, SUS_ampm, SUE_hour, SUE_minute, SUE_ampm];


            const set3_1 = [name];
            const set3_2 = [platformURL];
            const set3_3 = [launchDate];
            const set3_4 = [moduleName, deadlineDate];
            const set3_5 = [portalName, SUS_hour, SUS_minute, SUS_ampm, SUE_hour, SUE_minute, SUE_ampm];




            // IDs that need to unhide their grandparent's parent (.col)
            const deepUnhideIds = ['hour', 'ODT_hour', 'NDT_hour', 'SUS_hour'];

            // Toggle visibility
            function toggleVisibility(showSet = [], hideSets = []) {
                // Show
                showSet.forEach(el => {
                    if (el) {
                        el.classList.remove('d-none');
                        el.parentElement?.classList.remove('d-none');

                        if (deepUnhideIds.includes(el.id)) {
                            el.parentElement?.parentElement?.parentElement?.classList.remove('d-none');
                        }

                        el.setAttribute('required', '');
                    }
                });

                // Hide
                hideSets.flat().forEach(el => {
                    if (el) {
                        el.classList.add('d-none');
                        el.parentElement?.classList.add('d-none');

                        if (deepUnhideIds.includes(el.id)) {
                            el.parentElement?.parentElement?.parentElement?.classList.add('d-none');
                        }

                        el.removeAttribute('required');
                    }
                });
            }

            // Example: assuming `selected` is already defined somewhere
            if (selected === 'orientation_message') {
                toggleVisibility(set1, [set1_2, set2, set3]);
            } else if (selected === 'orientation_session_missout') {
                toggleVisibility(set1, [set1_1, set2, set3]);
            } else if (selected === 'class_rescheduled') {
                toggleVisibility(set2, [set1, set3]);
            } else if (selected === 'attendance__learner_missed_multiple_sessions') {
                toggleVisibility(set3, [set1, set2, set3_2, set3_3, set3_4, set3_5]);
            } else if (selected === 'new_ai_chatbot_rollout_support_channel') {
                toggleVisibility(set3, [set1, set2, set3_4, set3_5]);
            } else if (selected === 'payment_reminder__1_week_after_class_started') {
                toggleVisibility(set3, [set1, set2, set3_2, set3_3, set3_5]);
            } else if (selected === 'system_update_notification_any_platform') {
                toggleVisibility(set3, [set1, set2, set3_2, set3_4]);
            } else {
                toggleVisibility([], [all]);
            }
        }
    </script>

</body>

</html>