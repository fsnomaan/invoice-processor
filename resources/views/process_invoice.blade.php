@extends('layout')

@section('content')
    <div class="row">
        <div class="col-sm-12" align="center">
            <h1 class="bg-info text-white" align="center">Invoice Processor</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 bg-success text-white" align="center">
            {{ Session::get('notifications') }}
            {{ Session::forget('notifications') }}
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-sm-4">
            <div class="importTable">
                <h3>Import a bank statement</h3>

                {{ Form::open(array('url' => '/bank-statement', 'files' => true)) }}

                {{ Form::open(array('action' => 'BankStatementController@processBankStatement')) }}

                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="bankStatement">
                    <label class="custom-file-label" for="customFile">Choose file</label>
                </div>

                {{ Form::submit('Import') }}

                {{ Form::close() }}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="importTable">
                <h3>Import an open invoice</h3>
                {{ Form::open(array('url' => '/open-invoice', 'files' => true)) }}

                {{ Form::open(array('action' => 'OpenInvoiceController@processOpenInvoice')) }}

                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="openInvoice">
                    <label class="custom-file-label" for="customFile">Choose file</label>
                </div>

                {{ Form::submit('Import') }}

                {{ Form::close() }}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="mappingTable">
                {!! Form::open(['url' => '/update-map']) !!}
                {{ Form::open(array('action' => 'CompanyNameController@updateMap')) }}

                <table class="table-sm">
                    <thead>
                        <tr>
                            <th scope="col">Name</th><th scope="col">Map To</th>
                        </tr>
                    </thead>
                    @if(isset($companyNames))
                        @foreach ($companyNames as $name => $mapTo)
                            <tr scope="row">
                                <td>{{ $name }}</td>
                                <td>{{ $mapTo }}</td>
                                <td><button type="submit" name="actionName" value="<?php echo 'remove=>'. $name ?>"><i class="fa fa-trash"></i></button></td> </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td> {{ Form::text('mapName') }} </td>
                        <td> {{ Form::text('mapTo') }}</td>
                        <td><button type="submit" name="actionName" value="save"><i class="fa fa-check"></i></button></td>
                    </tr>
                </table>
                {{ Form::close() }}

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12" align="left">
            <div class="process">
                {!! Form::open(['url' => '/process-invoice']) !!}

                {{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}

                {{ Form::submit('Process Invoice') }}

                {{ Form::close() }}

            </div>
        </div>
    </div>





@stop