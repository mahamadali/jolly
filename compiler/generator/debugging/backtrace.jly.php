<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Huhhh! Error occured</title>
    <style>
        body {
            font-family: 'Trebuchet MS', 'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Arial, sans-serif;
            background-color: #f6f8fa;
            color: #24292e;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            background-color: rgb(239 239 239 / 60%);
            padding: 20px;
            box-sizing: border-box;
        }

        .error-card {
            background-color: #fff;
            box-shadow: 0 0 10px rgba(200, 200, 200, 0.3);
            border-radius: 6px;
            padding: 40px;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 20px;
            text-align: center;
        }

        .exception-message {
            background-color: #ffefef !important;
            color: #ff1f1f;
            padding: 10px;
            border: 1px solid #f9d1d1;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: left;
            font-weight: bold;
        }

        .error-block-container {
            background-color: #f1f1f1 !important;
            border: 1px solid #dddddd;
            border-radius: 4px;
            margin-bottom: 10px;
            padding: 10px;
        }

        .normal-code span {
            font-size: 15px;
            padding-top: 4px;
            padding-bottom: 4px;
            margin-bottom: 4px;
        }

        .highlighted-code span {
            color: #ff0000 !important;
            font-weight: bold !important;
        }

        .normal-code:not(.highlighted-code) span.line-number {
            color: #434343 !important;
            font-weight: bold !important;
        }

        .stack-trace {
            text-align: left;
        }

        .stack-frame {
            background-color: #f5f5f5;
            padding: 10px;
            border: 1px solid #efefef;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .stack-file {
            color: #434343; /* Darker grey color */
            padding-bottom: 2px;
        }

        .stack-line {
            color: #6a737d;
            font-size: 14px;
            padding-bottom: 4px;
        }

        .stack-function {
            color: #24292e; /* Black color */
        }

        .stack-class {
            color: #434343; /* Darker grey color */
        }

        .stack-type {
            color: #6a737d;
        }

    </style>
</head>

<body>
    <div class="container">
        <div class="error-card">
            
            <h1>Huhhh! Error occured.</h1>

            @php
                foreach ($backtrace as $trace) {
                    
                    $cause = isset($trace->args[0]) ? $trace->args[0] : '';

                    if (!empty($cause)) {
                        $error_message = $cause->message;

                        @endphp
                            <p class="exception-message">{{ $error_message }}</p>
                        @php

                        $error_file_contents = highlight_file($cause->file, true);

                        $error_block = '';

                        $file = explode ( '<br />', $error_file_contents );

                        $line_number = 1;

                        foreach ( $file as &$line ) {
                            if ($line_number == $cause->line) {
                                $error_block .= '<span class="normal-code highlighted-code"><span class="line-number">' . $line_number . '.</span> ' . $line . '</span><br>';
                            } else if ($line_number > ($cause->line - 4) && $line_number < ($cause->line + 4)) {
                                $error_block .= '<span class="normal-code"><span class="line-number">' . $line_number . '.</span> ' . $line . '</span><br>';
                            }
                            
                            $line_number++;
                        }

                        @endphp
                        
                        <p class="error-file-name">in {{ $cause->file . ' on line ' . $cause->line }}</p>
                        <p class="error-block-container">{{ $error_block }}</p>

                        @php

                        break;
                    }
                }
            @endphp

            <div class="stack-trace">
                <?php foreach ($backtrace as $frame) : ?>
                    <div class="stack-frame">

                        @if (isset($frame->file)):
                            <div class="stack-file">{{ $frame->file }}</div>
                        @elseif(isset($frame->class)):
                            <div class="stack-file">{{ $frame->class }}</div>
                        @endif

                        @if (isset($frame->line)):
                            <div class="stack-line">Line {{ $frame->line }}</div>
                        @endif

                        @if (isset($frame->function)):
                            <div class="stack-function">
                                Function: {{ $frame->function }}
                                @if (isset($frame->class)):
                                    | Class: {{ $frame->class }}
                                @endif
                            </div>
                        @endif

                        @if (!isset($frame->function) && isset($frame->class)):
                            <div class="stack-class">Class: {{ $frame->class }}</div>
                        @endif

                        @if (isset($frame->type)):
                            <!-- <div class="stack-type">{{ $frame->type }}</div> -->
                        @endif

                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</body>

</html>