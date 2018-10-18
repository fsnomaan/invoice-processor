@extends('layout')

@section('content')

<div class="response">  {{ $success }} </div>

<div class="importTable">
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
</div>

<div class="importTable">
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
</div>

<div class="mappingTable">
    {!! Form::open(['url' => '/update-map']) !!}
    {{ Form::open(array('action' => 'CompanyNameController@updateMap')) }}

    <table class="table-responsive-sm">
        <tr>
            <thead>
                <tr>
                    <th>Name</th><th>Map To</th>
                </tr>
            </thead>
        </tr>
    @foreach ($companyNames as $name => $mapTo)
        <tr><td>{{ $name }}</td><td>{{ $mapTo }}</td><td><button type="submit" name="actionName" value="<?php echo 'remove=>'. $name ?>">Remove</button></td> </tr>
    @endforeach
        <tr>
            <td> {{ Form::text('mapName') }} </td> <td> {{ Form::text('mapTo') }}</td><td><button type="submit" name="actionName" value="save">Save</button></td>
        </tr>
    </table>
    {{ Form::close() }}

</div>

<div class="process">
    {!! Form::open(['url' => '/process-invoice']) !!}

    {{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}

    {{ Form::submit('Process Invoice') }}

    {{ Form::close() }}

</div>
@stop