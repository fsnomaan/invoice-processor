<html>
<link href="{{ asset('css/app.css') }}" rel="stylesheet" type="text/css" >
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<meta name="csrf-token" content="{{ csrf_token() }}">
    <body>
    <div id="app" class="container">
    @section('content')
            <h1>Welcome to Invoice Processor</h1>
    @show
    </div>
    </body>
<script src="/js/app.js"></script>
<script src="/js/main.js"></script>
</html>