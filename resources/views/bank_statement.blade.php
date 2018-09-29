@extends('layout')

@section('content')
    <h1>Process a bank statement</h1>

{{ $success }}

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
{{ Form::open(array('url' => '/bank-statement', 'files' => true)) }}

{{ Form::open(array('action' => 'BankStatementController@processBankStatement')) }}

{{ Form::file('bankStatement') }}

{{ Form::submit('Process') }}

{{ Form::close() }}

@stop