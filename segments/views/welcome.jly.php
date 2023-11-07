@extends('app')

@block("title") {{ setting('app.title', 'Ali Rocks!') }} @endblock

@block("styles")
        <link rel="stylesheet" href="{{ url('assets/css/app.css') }}" />
@endblock

@block("content")
        <div class="flex-center position-ref full-height">
            <div class="content">
                <div class="title m-b-md">
                    {{ setting('app.title', 'Jolly - A tiny PHP Framework') }}
                </div>
                <div class="footer">
                    <span>- {{ trans('built_n_managing_by') }} {{ setting('app.author.name', 'Mohammad Ali Manknojiya [ manknojiya121@gmail.com ]') }}</span>
                </div>
            </div>
        </div>
@endblock

@block("scripts")
    <script src="{{ url('assets/js/app.js') }}" type="text/javascript"></script>
@endblock