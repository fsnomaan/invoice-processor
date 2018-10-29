@extends('layout')

@section('content')
    <div class="row">
        <div class="col-sm-12" align="center">
            <h1 class="bg-info text-white mb-3" align="center">Invoice Processor</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            {{ Form::open(array('url' => '/bank-statement', 'files' => true)) }}

            {{ Form::open(array('action' => 'BankStatementController@processBankStatement')) }}
            <div class="form-group">
                <input type="text" class="form-control" name="invoicePrimary" placeholder="Enter first part of invoice i.e 1125">
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="separator" placeholder="Please define separator. Default is ;">
            </div>
            <div class="custom-file">
                <input type="file" class="custom-file-input" name="bankStatement">
                <label class="custom-file-label" for="customFile">Select bank statement</label>
            </div>
            <div class="custom-file mt-3">
                <input type="file" class="custom-file-input" name="openInvoice">
                <label class="custom-file-label" for="customFile">Select invoice file</label>
            </div>
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary pull-right">Process Invoice</button>
            </div>
            {{ Form::close() }}
        </div>
        <div class="col-sm-6">
            <div class="mappingTable">
                {!! Form::open(['url' => '/update-map']) !!}
                {{ Form::open(array('action' => 'CompanyNameController@updateMap')) }}

                <table class="table-condensed" style="width: 100%;">
                    <thead class="bg-dark text-white-50">
                    <tr>
                        <th scope="col" class="text-center">Company Name</th>
                        <th scope="col" class="text-center">Map To</th>
                        <th scope="col" class="text-center">#</th>
                    </tr>
                    </thead>
                    @if(isset($companyNames))
                        @foreach ($companyNames as $name => $mapTo)
                            <tr scope="row">
                                <td class="text-center">{{ $name }}</td>
                                <td class="text-center">{{ $mapTo }}</td>
                                <td>
                                    <button class="alert-danger" type="submit" name="actionName" value="<?php echo 'remove=>'. $name ?>">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td class="text-center"> {{ Form::text('mapName') }} </td>
                        <td class="text-center"> {{ Form::text('mapTo') }}</td>
                        <td><button class="alert-success" type="submit" name="actionName" value="save"><i class="fa fa-check"></i></button></td>
                    </tr>
                </table>
                {{ Form::close() }}
        </div>
    </div>



    <div class="row">

    </div>
    <div class="row">
        <div class="col-sm-12" align="center">
            @if (Session::has('notifications'))
                <span class="alert alert-success">
                    {{ Session::get('notifications') }}
                    {{ Session::forget('notifications') }}
                </span>
            @endif
            @if ($errors->any())
                <ul class="col-sm-4 alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="row pt-3">
        <div class="col-sm-6 bg-grey">
            <h6 class="bg-warning"> Step 1 </h6>
            <div class="importTable">

                {{ Form::open(array('url' => '/bank-statement', 'files' => true)) }}

                {{ Form::open(array('action' => 'BankStatementController@processBankStatement')) }}

                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="bankStatement">
                    <label class="custom-file-label" for="customFile">Import a bank statement</label>
                </div>

                <button type="submit" class="btn btn-primary pull-right mt-2">Import</button>

                {{ Form::close() }}
            </div>
        </div>
        <div class="col-sm-6">
            <h6 class="bg-warning"> Step 2 </h6>
            <div class="importTable">

                {{ Form::open(array('url' => '/open-invoice', 'files' => true)) }}

                {{ Form::open(array('action' => 'OpenInvoiceController@processOpenInvoice')) }}
                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="openInvoice">
                    <label class="custom-file-label" for="customFile">Import an open invoice</label>
                </div>

                <button type="submit" class="btn btn-primary pull-right mt-2">Import</button>

                {{ Form::close() }}
            </div>
        </div>
    </div>
    <div class="row pt-3">
        <div class="col-sm-6">
            <h6 class="bg-warning"> Optional </h6>
            <div class="mappingTable">
                {!! Form::open(['url' => '/update-map']) !!}
                {{ Form::open(array('action' => 'CompanyNameController@updateMap')) }}

                <table class="table-condensed" style="width: 100%;">
                    <thead class="bg-dark text-white-50">
                        <tr>
                            <th scope="col" class="text-center">Company Name</th>
                            <th scope="col" class="text-center">Map To</th>
                            <th scope="col" class="text-center">#</th>
                        </tr>
                    </thead>
                    @if(isset($companyNames))
                        @foreach ($companyNames as $name => $mapTo)
                            <tr scope="row">
                                <td class="text-center">{{ $name }}</td>
                                <td class="text-center">{{ $mapTo }}</td>
                                <td>
                                    <button class="alert-danger" type="submit" name="actionName" value="<?php echo 'remove=>'. $name ?>">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td class="text-center"> {{ Form::text('mapName') }} </td>
                        <td class="text-center"> {{ Form::text('mapTo') }}</td>
                        <td><button class="alert-success" type="submit" name="actionName" value="save"><i class="fa fa-check"></i></button></td>
                    </tr>
                </table>
                {{ Form::close() }}

            </div>
        </div>
        <div class="col-sm-6" align="left">
            <h6 class="bg-warning"> Step 3 </h6>
            <div class="process pull-right">
                {!! Form::open(['url' => '/process-invoice']) !!}

                {{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}

                {{ Form::submit('Process Invoice') }}

                {{ Form::close() }}

            </div>
        </div>
    </div>





@stop