<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo $title; ?></h1>
            <nav>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/home/about">About</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <p><?php echo $description; ?></p>
            <div class="welcome-box">
                <h2>Welcome to your new MVC Application</h2>
                <p>This is a simple MVC framework built with PHP. You can use this as a starting point for your web applications.</p>
                <p>To get started, check out the following files:</p>
                <ul>
                    <li><code>controllers/HomeController.php</code> - The main controller</li>
                    <li><code>views/home/index.php</code> - This view file</li>
                    <li><code>models/</code> - Create your models here</li>
                    <li><code>core/</code> - The framework core files</li>
                </ul>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> MVC Framework</p>
        </footer>
    </div>
</body>
</html>