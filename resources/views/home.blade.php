@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                        <a
                        target="_new" 
                        href="
                            mailto:info@smart-allocation.com?subject=Request a demo for Smart Allocation&body=WE DO NOT SAVE CLIENT DATA ANYWHERE" 
                        class="btn btn-info float-right">Request a demo
                    </a>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    <img src="{{ asset('image/process.png') }}" style="max-width:90%">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection