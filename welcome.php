<?php 

?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Battle Council</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <style>
        /* ===== Base ===== */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f1115;
            color: #e6e6e6;
        }

        /* Header */
        .header-image img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        /* === Header Image === */
        .header-image {
            /*background: #4b0082;*/
            width: 100%;
            display: flex;           /* flex for centering */
            justify-content: center; /* horizontal centering */
            align-items: center;     /* vertical centering */
            padding-top: 10px;
        }

        .header-image img {
            width: 100%;
            max-width: 1280px;       /* caps the header image on large screens */
            max-height: 150px;
            object-fit: cover;
            border-bottom: 2px solid #6a0dad;
        }

        /* === NAVBAR === */
        .navbar {
            width: 100%;
            max-width: 1230px;       /* keeps it from stretching too far */
            margin: 0 auto;           /* centers the navbar container */
            position: sticky;
            top: 0;                   /* required for sticky */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #4b0082;
            color: white;
            padding: 10px 25px 10px 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;            /* keeps navbar above content */
        }

        .nav-left a {
            color: #fff;
            font-weight: 800;
            font-size: 1.25rem;
            text-decoration: none;
        }

        .nav-left a:hover {
            opacity: 0.9;
        }

        /* --- Menu --- */
        .menu-bar {
            background: #6a0dad;
            color: #fff;
            padding: 10px 20px;
            text-align: right;
        }

        .menu-bar a {
            color: #fff;
            margin-left: 20px;
            text-decoration: none;
            font-weight: bold;
        }

        .menu-bar a:hover {
            text-decoration: underline;
        }

        .menu {
            position: relative;
        }

        .menu-btn {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: 500;
            margin-left: -20px; /* move left */
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;   /* anchor to button edge */
            left: auto; /* prevent overflow */
            background: #fff;
            min-width: 150px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            overflow: hidden;
            z-index: 1000;
        }

        .menu-content a {
            display: block;
            padding: 8px 12px;
            color: #333;
            text-decoration: none;
            font-size: 0.95em;
        }

        .menu-content a:hover {
            background: #eee;
        }

        .menu:hover .menu-content {
            display: block;
        }


        /* Layout Wrapper */
        .bc-container {
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        /* Single Card Wrapper */
        .bc-grid {
            width: 100%;
            max-width: 800px;   /* ✅ half the previous max width */
        }

        /* Card */
        .bc-card {
            width: 100%;
            background: #1b2130;
            border-radius: 18px;
            overflow: hidden;
            color: #fff;
            box-shadow: 0 8px 28px rgba(0,0,0,.6);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .bc-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 34px rgba(0,0,0,.75);
        }

        /* Video */
        .bc-img video {
            width: 100%;
            height: 280px;      /* ✅ taller video */
            object-fit: cover;
        }

        /* Content */
        .bc-content {
            padding: 20px 24px;
        }

        .bc-content h2 {
            margin: 0 0 12px;
            font-size: 24px;
            line-height: 1.2;
        }

        .bc-content p {
            font-size: 15px;
            line-height: 1.6;
            color: #cfcfcf;
            margin-bottom: 12px;
        }

        .bc-content ul {
            margin-top: 12px;
            padding-left: 18px;
            font-size: 14px;
            color: #aaa;
        }

        /* Tablet */
        @media (min-width: 768px) {
            .bc-img video {
                height: 340px;
            }
            .bc-content h2 {
                font-size: 28px;
            }
            .bc-content p {
                font-size: 16px;
            }
        }
        /* Desktop */
        @media (min-width: 1024px) {
            .bc-grid {
                max-width: 800px;   /* maintain half width */
            }
            .bc-img video {
                height: 420px;
            }
            .bc-content h2 {
                font-size: 32px;
            }
            .bc-content p {
                font-size: 17px;
            }
        }
        </style>

    </head>

    <body>
        <!-- Header -->
        <div class="header-image">
            <img src="/images/site-header-2.png" alt="Battle Council">
        </div>
        <!-- Nav -->
        <nav class="navbar">
            <div class="nav-left">
                <a href="index.php" class="nav-link" >Home</a>
            </div>
        </nav>
        <!-- Main -->
        <div class="bc-container">
            <div class="bc-grid">

                <!-- Welcome Card -->
                <div class="bc-card">
                    <div class="bc-img">
                            <video src="/images/trent/Trent_the_Elder_generated.mp4" 
                            alt="Battle Council Video"
                            controls
                            autoplay
                            muted
                            loop
                            style="border-radius:8px;">
                            Your browser does not support the video tag.
                        </video>
                    </div>

                    <div class="bc-content">
                        <h2>Command the Hunt. Control the Outcome.</h2>

                        <p>
                            Battle Council is built for players who don’t guess — they calculate. 
                            Every hunt, every squad, every creature choice matters. This is where you turn scattered data into clean, repeatable wins.
                        </p>

                        <p>
                            Plan smarter attacks using real matchup logic, creature bonuses, and survivability math. 
                            Instead of over-sending or guessing losses, you’ll know exactly what to deploy — and why it works.
                        </p>

                        <p>
                            Whether you’re optimizing monster hunts, testing formations, or scaling your efficiency, 
                            this is your command layer above the game.
                        </p>

                        <ul>
                            <li>Creature vs monster optimization</li>
                            <li>Attack formation builder</li>
                            <li>Loss + efficiency calculations</li>
                            <li>Scalable strategy tools (in progress)</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>

        <div style="height:50px;"></div>

        <?php include 'includes/footer.php'; ?>

    </body>
</html>