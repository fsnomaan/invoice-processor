<html>
<link href="{{ asset('css/app.css') }}" rel="stylesheet" type="text/css" >
<link href="{{ asset('css/style.css') }}" rel="stylesheet" type="text/css" >
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
<link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700" rel="stylesheet">
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