<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'] ?? '/';

require 'db.php';
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
};

$username = $_SESSION['username'] ?? '';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

?>


<!DOCTYPE html>
<html>

<head>
  <title>WhatsApp Message Sender</title>

  <!-- bootstrap 5.3.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- font-awesome 6.7.2 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body class="bg-light">

  <div class="container">

    <?php include 'components/navbar.php'; ?>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <strong><?= $flash['message'] ?></strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>


    <h4 class="mb-3">Send WhatsApp Message</h4>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="row">
          <div class="col-6">

            <form method="POST" action="functions/send.php" enctype="multipart/form-data" class="needs-validation" id="myForm" novalidate>

              <div class="mb-3">
                <label for="message_type" class="form-label">Message Type</label>
                <select class="form-select" name="message_type" id="message_type" required>
                  <option value="template">Template</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="phone" class="form-label">Phone Number(s)</label>
                <input type="text" class="form-control" id="phone" name="phone" placeholder="65551234567,65551234567">
                <div class="invalid-feedback">
                  Enter one / more numbers WITH COUNTRY CODE separated by commas e.g.: 94787189456, 15551234567 or Upload a CSV file.
                </div>

              </div>

              <div class="mb-3">
                <label for="csv" class="form-label">Upload CSV</label>
                <input type="file" class="form-control" id="csv" name="csv" accept=".csv">
                <div class="form-text">Upload a CSV file with one phone number per line.</div>
              </div>

              <div class="mb-3">
                <label for="tag" class="form-label">Cohort Name</label>
                <input type="text" class="form-control" id="tag" name="tag" placeholder="BDSE-1222" required>
                <div class="invalid-feedback">
                  Enter A Cohort Name
                </div>

              </div>

              <div>
                <?php include 'templates.php'; ?>
              </div>

              <div id="tempVariableSec" class="">
                <div id="variableFields">
                  <div class="row row-cols-2">

                    <div class="mb-3 col d-none">
                      <label for="courseName" class="form-label">{{1}} Course / Session Name </label>
                      <input type="text" class="form-control" id="courseName" name="courseName" placeholder="Python Basics" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="pillarEmail" class="form-label">{{2}} Pillar Support Email </label>
                      <select class="form-select" id="pillarEmail" name="pillarEmail" required>
                        <option value="" disabled selected>Select Email</option>
                        <option value="asksm@lithan.com">asksm@lithan.com</option>
                        <option value="askdb@lithan.com">askdb@lithan.com</option>
                        <option value="askds@lithan.com">askds@lithan.com</option>
                        <option value="askwd@lithan.com">askwd@lithan.com</option>
                      </select>
                    </div>
                    <div class="mb-3 col d-none">
                      <label for="tempDate" class="form-label">{{2}} Date </label>
                      <input type="date" class="form-control" id="tempDate" name="tempDate" required>
                    </div>

                    <div class="col d-none">
                      <div class="row mb-3">
                        <div class="col d-none">
                          <label class="form-label"> {{3}} Hour</label>
                          <select class="form-select" id="hour" name="hour" required selected>
                            <option value="" disabled selected>--</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                              <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">Minute</label>
                          <select class="form-select" id="minute" name="minute" required>
                            <option value="" disabled selected>--</option>
                            <option>00</option>
                            <option>15</option>
                            <option>30</option>
                            <option>45</option>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">AM/PM</label>
                          <select class="form-select" id="ampm" name="ampm" required>
                            <option value="" disabled selected>--</option>
                            <option>AM</option>
                            <option>PM</option>
                          </select>
                        </div>
                      </div>
                    </div>


                    <div class="mb-3 col d-none">
                      <label for="Original_Date" class="form-label">{{1}} Original_Date</label>
                      <input type="date" class="form-control" id="Original_Date" name="Original_Date" required>
                    </div>

                    <div class="col d-none">
                      <div class="row mb-3">
                        <div class="col d-none">
                          <label class="form-label"> ODT_hour</label>
                          <select class="form-select" id="ODT_hour" name="ODT_hour" required>
                            <option value="" disabled selected>--</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                              <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">Minute</label>
                          <select class="form-select" id="ODT_minute" name="ODT_minute" required>
                            <option value="" disabled selected>--</option>
                            <option>00</option>
                            <option>15</option>
                            <option>30</option>
                            <option>45</option>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">AM/PM</label>
                          <select class="form-select" id="ODT_ampm" name="ODT_ampm" required>
                            <option value="" disabled selected>--</option>
                            <option>AM</option>
                            <option>PM</option>
                          </select>
                        </div>
                      </div>
                    </div>



                    <div class="mb-3 col d-none">
                      <label for="New_Date" class="form-label">{{1}} New_Date</label>
                      <input type="date" class="form-control" id="New_Date" name="New_Date" required>
                    </div>

                    <div class="col d-none">
                      <div class="row mb-3">
                        <div class="col d-none">
                          <label class="form-label"> NDT_hour</label>
                          <select class="form-select" id="NDT_hour" name="NDT_hour" required>
                            <option value="" disabled selected>--</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                              <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">Minute</label>
                          <select class="form-select" id="NDT_minute" name="NDT_minute" required>
                            <option value="" disabled selected>--</option>
                            <option>00</option>
                            <option>15</option>
                            <option>30</option>
                            <option>45</option>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">AM/PM</label>
                          <select class="form-select" id="NDT_ampm" name="NDT_ampm" required>
                            <option value="" disabled selected>--</option>
                            <option>AM</option>
                            <option>PM</option>
                          </select>
                        </div>
                      </div>
                    </div>




                    <div class="mb-3 col d-none">
                      <label for="tempLocation" class="form-label">{{4}} Location </label>
                      <input class="form-control" id="tempLocation" name="tempLocation" placeholder="Location" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="name" class="form-label">{{1}} Name / Learner</label>
                      <input type="text" class="form-control" id="name" name="name" placeholder="Hashan Rathnayake" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="platformURL" class="form-label">{{2}} Platform / URL</label>
                      <input type="text" class="form-control" id="platformURL" name="platformURL" placeholder="https://" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="portalName" class="form-label">{{2}} System Name: Portal</label>
                      <input type="text" class="form-control" id="portalName" name="portalName" placeholder="LMS" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="moduleName" class="form-label">{{2}} Module Name </label>
                      <input type="text" class="form-control" id="moduleName" name="moduleName" placeholder="Python Module" required>
                    </div>

                    <div class="mb-3 col d-none">
                      <label for="launchDate" class="form-label">{{3}} Date</label>
                      <input type="date" class="form-control" id="launchDate" name="launchDate" required>
                    </div>


                    <div class="mb-3 col d-none">
                      <label for="deadlineDate" class="form-label">{{3}} Deadline Date </label>
                      <input type="date" class="form-control" id="deadlineDate" name="deadlineDate" required>
                    </div>

                    <div class="col d-none">
                      <div class="row mb-3">
                        <p> {{4}} Start:</p>
                        <div class="col d-none">
                          <label class="form-label"> Hours</label>
                          <!-- <label class="form-label"> {{4}} Start: Hours</label> -->
                          <select class="form-select" id="SUS_hour" name="SUS_hour" required>
                            <option value="" disabled selected>--</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                              <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">Minute</label>
                          <select class="form-select" id="SUS_minute" name="SUS_minute" required>
                            <option value="" disabled selected>--</option>
                            <option>00</option>
                            <option>15</option>
                            <option>30</option>
                            <option>45</option>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">AM/PM</label>
                          <select class="form-select" id="SUS_ampm" name="SUS_ampm" required>
                            <option value="" disabled selected>--</option>
                            <option>AM</option>
                            <option>PM</option>
                          </select>
                        </div>
                      </div>

                      <div class="row mb-3">
                        <p> {{5}} End: </p>
                        <div class="col d-none">
                          <label class="form-label"> Hours</label>
                          <select class="form-select" id="SUE_hour" name="SUE_hour" required>
                            <option value="" disabled selected>--</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                              <option value="<?= $h ?>"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">Minute</label>
                          <select class="form-select" id="SUE_minute" name="SUE_minute" required>
                            <option value="" disabled selected>--</option>
                            <option>00</option>
                            <option>15</option>
                            <option>30</option>
                            <option>45</option>
                          </select>
                        </div>
                        <div class="col d-none">
                          <label class="form-label">AM/PM</label>
                          <select class="form-select" id="SUE_ampm" name="SUE_ampm" required>
                            <option value="" disabled selected>--</option>
                            <option>AM</option>
                            <option>PM</option>
                          </select>
                        </div>
                      </div>
                    </div>


                  </div>
                </div>

              </div>
              <button type="submit" class="btn btn-success">Send Message</button>
            </form>

          </div>

          <div class="col-6">
            <div class="d-flex flex-row justify-content-end">
              <div id="language2" class="text-center mb-2 me-3"></div>
              <div id="status" class="text-end mb-2"></div>

            </div>
            <div id="components" class=""></div>

            <?php if ($flash && ($_SESSION['role'] ?? '') === 'admin'): ?>
              <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show p-0" role="alert">
                <?php if (!empty($flash['response_log'])): ?>
                  <div style="height: 500px; overflow: auto; font-size: 1em;">
                    <pre><?= htmlspecialchars(json_encode($flash['response_log'], JSON_PRETTY_PRINT)) ?></pre>
                  </div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php endif; ?>

          </div>
        </div>

      </div>
    </div>


  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const input1 = document.getElementById('phone');
    const input2 = document.getElementById('csv');
    const form = document.getElementById('myForm');

    const updateRequiredAttributes = () => {
      const val1 = input1.value.trim();
      const val2 = input2.value.trim();

      if ((val1 && val2) || (!val1 && !val2)) {
        input1.setAttribute('required', '');
        input2.setAttribute('required', '');
      } else if (val1) {
        input1.setAttribute('required', '');
        input2.removeAttribute('required');
      } else if (val2) {
        input2.setAttribute('required', '');
        input1.removeAttribute('required');
      }
    };

    input1.addEventListener('input', updateRequiredAttributes);
    input2.addEventListener('input', updateRequiredAttributes);

    form.addEventListener('submit', function(e) {
      updateRequiredAttributes();

      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        // console.log("Form is invalid. Blocked from submitting.");
      } else {
        // console.log("Form is valid. Submitting...");
      }

      form.classList.add('was-validated');
    });
  </script>
</body>

</html>