<html>

<head>
    <title>404: Page not found</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style type="text/css">
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI Light', Tahoma, Geneva, Verdana, sans-serif;
        }

        html {
            height: 100%;
        }

        .dt {
            display: table;
        }

        .dtc {
            display: table-cell;
        }

        .fw6 {
            font-weight: 600;
        }

        .vh-100 {
            height: 100vh;
        }

        .w-100 {
            width: 100%;
        }

        .white {
            color: #fff;
        }

        .bg-dark-orange {
            background-color: #c73328c9;
        }

        .ph3 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .tc {
            text-align: center;
        }

        .f6 {
            font-size: .875rem;
            margin-bottom: 0px;
        }

        .v-mid {
            vertical-align: middle;
        }

        @media screen and (min-width: 30em) and (max-width: 60em) {
            .f2-m {
                font-size: 2.25rem;
            }
        }

        @media screen and (min-width: 60em) {
            .ph4-l {
                padding-left: 2rem;
                padding-right: 2rem;
            }

            .f-subheadline-l {
                font-size: 5rem;
            }
        }
    </style>
</head>

<body>
    <article class="vh-100 dt w-100 bg-dark-orange">
        <div class="dtc v-mid tc white ph3 ph4-l">
            <h1 class="f6 f2-m f-subheadline-l fw6 tc">404</h1>
            <h3>{{ !empty($data['error']) ? $data['error'] : 'Page you are looking for does not exists in the system' }}</h3>
        </div>
    </article>
</body>

</html>