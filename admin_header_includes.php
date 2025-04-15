
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Cupid Admin</title>

<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<!-- Admin panel CSS -->
<link href="assets/css/admin.css" rel="stylesheet">

<!-- jQuery (needed for some admin functionalities) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- Optional: Include Chart.js if you're using charts -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script> -->

<!-- Custom admin styles -->
<style>
    /* You can add any additional custom styles here */
    .admin-container {
        padding-top: 80px;
        display: flex;
        min-height: calc(100vh - 80px);
    }
    
    .admin-container .container {
        display: flex;
        width: 100%;
    }
    
    .main-content {
        flex: 1;
        padding: 0 20px 20px 20px;
    }
    
    @media (max-width: 768px) {
        .admin-container .container {
            flex-direction: column;
        }
    }
</style>