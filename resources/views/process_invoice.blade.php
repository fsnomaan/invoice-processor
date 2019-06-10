@extends('layouts.app')

@section('content')
<div class="container">
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Home</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Customer Name Mapping</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">Bank Account Mapping</a>
        </li>
  </ul>
</div>
<div class="container-flex mt-3">
  <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="row">
                <div class="col-sm-4 offset-4 mb-3">
                    {{ Form::open(array('url' => '/process-invoice', 'files' => true)) }}
                    {{ Form::open(array('action' => 'ProcessInvoiceController@processInvoice')) }}
                    <div class="form-group">
                        <input type="text" class="form-control" name="separator" placeholder="Enter delimiter">
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
                        <input type="hidden" name="userId" value="{{ $userId }}">
                        <button type="submit" class="btn btn-primary float-right">Match Invoices</button>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="row">
                <div class="col-sm-4 offset-4 mb-3">
                    <div class="mappingTable">
                        <form method="POST" accept-charset="UTF-8" name="frm-map-company-name" id="frm-map-company-name">
                        <table id="tbl-map-company" class="table-condensed table-hover option" style="width: 100%;">
                            <thead>
                            <tr>
                                <th scope="col" class="text-left">Statement Name</th>
                                <th scope="col" class="text-left">Customer Name</th>
                                <th scope="col" class="text-left"></th>
                            </tr>
                            </thead>
                            <tr>
                                <td class="text-left"> {{ Form::text('mapName', null, array('size'=>30)) }} </td>
                                <td class="text-left"> {{ Form::text('mapTo', null, array('size'=>30)) }}</td>
                                <input type="hidden" name="userId" value="{{ $userId }}">
                                <td class="text-right">
                                    <button type="submit" value="save" id="mapCompanyName" title="Add Mapping" class="text-primary option">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                        @if(isset($companyNames))
                            @foreach ($companyNames as $company)
                                    <tr scope="row">
                                        <td class="text-left">{{ $company->name }}</td>
                                        <td class="text-left">{{ $company->map_to }}</td>
                                        <td class="text-right">
                                        <button type="submit" value="remove" title="Remove Mapping" class="text-danger option" data-id="{{ $company->id }}">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </table>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
            <div class="row">
                <div class="col-sm-4 offset-4 mb-3">
                    <div class="mappingTable">
                        <form method="POST" accept-charset="UTF-8" name="frm-map-account" id="frm-map-account">
                            <table id="tbl-map-account" class="table-condensed table-hover option" style="width: 100%;">
                            <thead>
                            <tr>
                                <th scope="col" class="text-left">Statement Account Number</th>
                                <th scope="col" class="text-left">Bank Account Id</th>
                                <th scope="col" class="text-left"></th>
                            </tr>
                            </thead>
                            <tr>
                                <td class="text-left"> {{ Form::text('mapNumber') }} </td>
                                <td class="text-left"> {{ Form::text('mapTo') }}</td>
                                <input type="hidden" name="userId" value="{{ $userId }}">
                                <td class="text-right">
                                    <button type="submit" value="save" title="Add Mapping" class="text-primary option">
                                        <i class="fa fa-plus" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                            @if(isset($bankAccounts))
                                @foreach ($bankAccounts as $account)
                                    <tr scope="row">
                                        <td class="text-left">{{ $account->bank_acc_number }}</td>
                                        <td class="text-left">{{ $account->bank_acc_id }}</td>
                                        <td class="text-right">
                                            <button type="submit" value="remove" title="Remove Mapping" class="text-danger option" data-id="{{ $account->id }}">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </table>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
@section('pagescript')
    <script src="{{ asset('js/process_invoice.js') }}" defer></script>
    <script src="http://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
@stop