<html>

<head>
    <title>Jolly: <?php echo (!empty($error) ? $error : 'Error'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">

        body {
            background: #ffc6c663;
            overflow: hidden;
            font-family: Menlo, Monaco, "Consolas", "Courier New", "Courier";
        }

        .terminal-window {
            text-align: left;
            width: 80%;
            height: min-content;
            border-radius: .625rem;
            margin: auto;
            position: relative;
            top: 0.5rem;
        }

        .terminal-window header {
            background: #fbfdff;
            height: 1.875rem;
            border-radius: .5rem .5rem 0 0;
            padding-left: .625rem;
        }

        .terminal-window header .button {
            width: .75rem;
            height: .75rem;
            margin: .625rem .25rem 0 0;
            display: inline-block;
            border-radius: .5rem;
        }

        .terminal-window header .button.green {
            background: #3BB662;
        }

        .terminal-window header .button.yellow {
            background: #E5C30F;
        }

        .terminal-window header .button.red {
            background: #E75448;
        }

        .terminal-window section.terminal {
            color: white;
            font-size: 11pt;
            background: #d72434;
            padding: .625rem;
            box-sizing: border-box;
            width: 100%;
            top: 1.875rem;
            bottom: 0;
            overflow: auto;
            height: min-content;
        }
        .terminal-window .file-name {
            display: inline-block;
        }
        .terminal-window .framework-title {
            display: inline-block;
            float: right;
            padding: 5px;
        }

    </style>
</head>

<body>
    <div class="terminal-window">
        <header>
            <div class="button red"></div>
            <div class="file-name">
                Error detected
            </div>
            <div class="framework-title">
                :Jolly
            </div>
        </header>
        
            <section class="terminal">
                <div class="history"></div>
                <?php
                    if (!empty($error))
                        echo $error;
                    else
                        echo 'Oops! Something went wrong...';
                ?>
            </section>
    </div>
</body>

</html>