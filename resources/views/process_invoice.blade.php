@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12" align="center">
            <h2 class="m-3 bg-light" align="left">Invoice Matching Processor</h2><hr>
        </div>
    </div>

    <div class="row pb-3">
        <div class="col-sm-12" align="center">
            @if ($errors->any())
                <ul class="col-sm-4 alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6 offset-3 mb-3">
            {{ Form::open(array('url' => '/process-invoice', 'files' => true)) }}

            {{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}
            <div class="form-group">
                <input type="text" class="form-control" name="invoiceFirstPart" placeholder="Enter invoice prefix">
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="separator" placeholder="Specify field separator. Default is ;">
            </div>
            <div class="custom-file">
                <input type="file" class="custom-file-input" name="bankStatement">
                <label class="custom-file-label" for="customFile">Select Bank statement</label>
            </div>
            <div class="custom-file mt-3">
                <input type="file" class="custom-file-input" name="openInvoice">
                <label class="custom-file-label" for="customFile">Select Open Invoices</label>
            </div>
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary float-right">Match Invoices</button>
            </div>
            {{ Form::close() }}
        </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6">
            <div class="mappingTable">
                {!! Form::open(['url' => '/map-company-name']) !!}
                {{ Form::open(array('action' => 'MappingController@mapCompanyName')) }}

                <table class="table-condensed option" style="width: 100%;">
                    <thead class="bg-info">
                    <tr>
                        <th colspan="3" class="border-bottom text-white text-center">Customer Name Mapping</th>
                    </tr>
                    <tr>
                        <th scope="col" class="text-center">Company Name</th>
                        <th scope="col" class="text-center">Customer Name</th>
                        <th scope="col" class="text-center"></th>
                    </tr>
                    </thead>
                    @if(isset($companyNames))
                        @foreach ($companyNames as $name => $mapTo)
                            <tr scope="row">
                                <td class="text-center">{{ $name }}</td>
                                <td class="text-center">{{ $mapTo }}</td>
                                <td>
                                    <button title="Remove Mapping" class="text-danger option" type="submit" name="actionName" value="<?php echo 'remove=>'. $name ?>">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td class="text-center"> {{ Form::text('mapName') }} </td>
                        <td class="text-center"> {{ Form::text('mapTo') }}</td>
                        <td><button title="Add Mapping" class="text-success option" type="submit" name="actionName" value="save">
                                <i class="fas fa-plus"></i>
                            </button>
                        </td>
                    </tr>
                </table>
                {{ Form::close() }}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="mappingTable">
                {!! Form::open(['url' => '/map-bank-number']) !!}
                {{ Form::open(array('action' => 'MappingController@mapBankAccountNumber')) }}

                <table class="table-condensed option" style="width: 100%;">
                    <thead class="bg-info">
                    <tr>
                        <th colspan="3" class="border-bottom text-white text-center">Bank Account Mapping</th>
                    </tr>
                    <tr>
                        <th scope="col" class="text-center">Bank Account Number</th>
                        <th scope="col" class="text-center">Bank Account Id</th>
                        <th scope="col" class="text-center"></th>
                    </tr>
                    </thead>
                    @if(isset($bankAccounts))
                        @foreach ($bankAccounts as $number => $mapTo)
                            <tr scope="row">
                                <td class="text-center">{{ $number }}</td>
                                <td class="text-center">{{ $mapTo }}</td>
                                <td>
                                    <button title="Remove Mapping" class="option text-danger" type="submit" name="actionName" value="<?php echo 'remove=>'. $number ?>">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td class="text-center"> {{ Form::text('mapNumber') }} </td>
                        <td class="text-center"> {{ Form::text('mapTo') }}</td>
                        <td><button title="Add Mapping" class="text-success option" type="submit" name="actionName" value="save">
                                <i class="fas fa-plus"></i>
                            </button>
                        </td>
                    </tr>
                </table>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
@stop
