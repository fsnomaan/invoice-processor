@extends('layout')

@section('content')
<center><h1> {{ $success }} </h1></center>

    <h1>Import a bank statement</h1>

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

{{ Form::submit('Import') }}

{{ Form::close() }}

<hr>

<h1>Import an open invoice</h1>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
{{ Form::open(array('url' => '/open-invoice', 'files' => true)) }}

{{ Form::open(array('action' => 'OpenInvoiceController@processOpenInvoice')) }}

{{ Form::file('OpenInvoice') }}

{{ Form::submit('Import') }}

{{ Form::close() }}

<hr>

{!! Form::open(['url' => '/process-invoice']) !!}

{{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}

{{ Form::submit('Process Invoice') }}

{{ Form::close() }}
@stop