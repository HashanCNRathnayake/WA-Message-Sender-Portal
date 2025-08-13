<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>


<style>
    html {
        overflow: auto;
        scrollbar-width: none;
        /* Firefox */
        -ms-overflow-style: none;
        /* IE and Edge */
    }

    html::-webkit-scrollbar {
        display: none;
        /* Chrome, Safari, Opera */
    }


    .nav-btn {
        background-color: white;
        padding: 2px 5px;
    }
</style>



<nav class="navbar navbar-expand-lg mb-2">
    <div class="container-fluid px-0">

        <a class="navbar-brand"
            href="<?= htmlspecialchars($baseUrl) ?>index.php">
            MyApp

        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav ms-auto me-5 mb-2 mb-lg-0">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($baseUrl) ?>admin/dashboard.php">Dashboard</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($baseUrl) ?>admin/users.php">Manage Users</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($baseUrl) ?>history.php">Message History</a>
                </li>
            </ul>
            <div class="d-flex">
                <a href="<?= htmlspecialchars($baseUrl) ?>functions/logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>