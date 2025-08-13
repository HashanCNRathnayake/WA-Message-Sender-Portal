<?php
date_default_timezone_set('Asia/Singapore');

session_start();

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'] ?? '/';
$response = null;


require '../db.php';
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// === CONFIG ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $templateName = $_POST['templateName']; // Must be unique and lowercase with 
    $templateLanguage = $_POST['templateLanguage'];
    $category = $_POST['category'];
    $headerText = $_POST['headerText'];
    $headerExample = $_POST['headerExample'];
    $bodyText = $_POST['bodyText'];
    $bodyExample = $_POST['bodyExample'];
    $footerText = $_POST['footerText'];

    // === CURL INIT ===
    $payload = [
        "name" => $templateName,
        "language" => $templateLanguage,
        "category" => $category,
        "components" => [
            [
                "type" => "HEADER",
                "format" => "TEXT",
                "text" => $headerText,
                "example" => [
                    "header_text" => [
                        $headerExample
                    ]
                ]

            ],
            [
                "type" => "BODY",
                "text" => $bodyText,
                "example" => [
                    "body_text" => [
                        $bodyExample
                    ]
                ]
            ],
            [
                "type" => "FOOTER",
                "text" => $footerText
            ]
        ]
    ];

    $ch = curl_init("https://graph.facebook.com/v23.0/" . $_ENV['BUSINESS_ACCOUNT_ID'] . "/message_templates");

    // $ch = curl_init("http://localhost/whatsapp-app/admin/test_receiver.php");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $_ENV['WHATSAPP_TOKEN']

    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_POST, true);
    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);


    // === OUTPUT ===
    header('Content-Type: application/json');
    echo json_encode([
        "status" => $httpStatus,
        "response" => json_decode($response, true)
    ], JSON_PRETTY_PRINT);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>WhatsApp Template Form</title>
    <style>
        .hidden {
            display: none;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container">
        <?php include '../components/navbar.php'; ?>


        <div class="row">
            <!-- FORM SECTION -->
            <div class="col-md-6">
                <h4 class="mb-2">WhatsApp Template Form</h4>

                <?php if ($response): ?>
                    <div class="alert alert-info">API Response: <?= htmlspecialchars($response) ?></div>
                <?php endif; ?>

                <form method="POST" id="templateForm" class="needs-validation" novalidate>
                    <div class="mb-1">
                        <label class="form-label">Template Name</label>
                        <span class="form-text">: Only lowercase letters and underscores allowed</span>
                        <input type="text" class="form-control" name="templateName" id="templateName" required pattern="^[a-z_]+$">
                        <div class="invalid-feedback">Template name is required.</div>
                    </div>

                    <div class="d-flex flex-row mb-4">
                        <div class="mb-1 flex-grow-1 me-2">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="templateLanguage" id="templateLanguage" required>
                                <option value="en_US" selected>English (US)</option>
                            </select>
                            <div class="invalid-feedback">Language is required.</div>
                        </div>

                        <div class="mb-1 flex-grow-1">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="category" required>
                                <option value="MARKETING" selected>Marketing</option>
                                <option value="UTILITY">Utility</option>
                            </select>
                            <div class="invalid-feedback">Category is required.</div>
                        </div>
                    </div>

                    <h6>Component</h6>
                    <div class="mb-1">
                        <label class="form-label">Header Text</label>
                        <input type="text" class="form-control" name="headerText" id="headerText" required>
                        <div class="invalid-feedback">Header text is required.</div>
                    </div>
                    <div class="mb-1 hidden" id="headerExampleGroup">
                        <label class="form-label">Header Variable: Name</label>
                        <input type="text" class="form-control" name="headerExample">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Body Text</label>
                        <textarea class="form-control" name="bodyText" id="bodyText" rows="4" required></textarea>
                        <div class="invalid-feedback">Body text is required.</div>
                    </div>
                    <div class="mb-1 hidden" id="bodyExampleGroup">
                        <label class="form-label">Body Variable: Course Name, Pillar Email</label>
                        <input type="text" class="form-control" name="bodyExample">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Footer Text</label>
                        <input type="text" class="form-control" name="footerText" id="footerText" required>
                        <div class="invalid-feedback">Footer text is required.</div>
                    </div>

                    <button type="submit" class="btn btn-success btn mt-3">Create</button>
                </form>
            </div>
            <div class="col-md-6">
                <div>
                    <?php include '../templates.php'; ?>

                    <div class="d-flex flex-row justify-content-end">
                        <div id="language2" class="text-center mb-2 me-3"></div>
                        <div id="status" class="text-end mb-2"></div>

                    </div>
                    <div id="components" class="p-3"></div>


                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('templateName').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z_]/g, '_');
        });

        const form = document.getElementById('templateForm');
        const headerExampleGroup = document.getElementById('headerExampleGroup');
        const bodyExampleGroup = document.getElementById('bodyExampleGroup');

        function toggleExample(input, exampleGroup) {
            if (/\{\{.*?\}\}/.test(input.value)) {
                exampleGroup.classList.remove('hidden');
                exampleGroup.querySelector('input').setAttribute('required', 'required');
            } else {
                exampleGroup.classList.add('hidden');
                exampleGroup.querySelector('input').removeAttribute('required');
            }
        }

        headerText.addEventListener('input', () => {
            toggleExample(headerText, headerExampleGroup);
        });

        bodyText.addEventListener('input', () => {
            toggleExample(bodyText, bodyExampleGroup);
        });



        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    </script>
</body>

</html>