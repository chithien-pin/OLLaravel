@extends('include.app')
@section('header')
    <style>
        /* Payment Gateway column with proper tooltip support */
        .table td:nth-child(6), 
        .table th:nth-child(6) {
            width: 160px !important;
            max-width: 160px !important;
            min-width: 160px !important;
            word-wrap: break-word;
        }
        
        /* Allow tooltip to display properly */
        .table td:nth-child(6) {
            overflow: visible !important;
            white-space: normal !important;
            position: relative;
        }
        
        /* Header should not truncate */
        .table th:nth-child(6) {
            white-space: normal !important;
            overflow: visible !important;
        }
        
        /* Compact action column with uniform buttons */
        .table td:nth-child(7), 
        .table th:nth-child(7) {
            width: 100px !important;
            max-width: 100px !important;
            min-width: 100px !important;
            text-align: center;
            vertical-align: middle !important;
        }
        
        /* Uniform action buttons */
        .btn-compact {
            padding: 4px 8px !important;
            font-size: 11px !important;
            border-radius: 4px !important;
            margin: 1px 0 !important;
            width: 70px !important;
            min-width: 70px !important;
            max-width: 70px !important;
            text-align: center;
            font-weight: 500;
            border: none !important;
            display: block;
        }
        
        /* Stack buttons vertically */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: center;
        }
        
        /* Make table responsive without breaking layout */
        .table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        
        /* Compact display for payment info */
        .payment-compact {
            display: inline-block;
            max-width: 130px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Modal form styling */
        #viewRequest .form-control {
            font-size: 13px !important;
            padding: 6px 8px !important;
        }
        
        #viewRequest label {
            font-size: 12px !important;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        #viewRequest .card-body {
            padding: 15px !important;
        }
        
        #viewRequest .col-md-6 {
            padding: 0 8px !important;
        }
        
        #viewRequest .row {
            margin: 0 -8px !important;
        }
    </style>
    <script src="{{ asset('asset/script/redeemRequests.js') }}"></script>
@endsection
@section('content')

    <div class="card">
        <div class="card-header">
            <h4>{{ __('app.Redeem_Requests') }}</h4>
            <div class="ms-3 card-tab">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                     <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                            aria-controls="home" role="tab" data-toggle="tab">{{__('app.Pending_Requests')}}<span
                                class="badge badge-transparent total_open_complaint"></span></a>
                    </li>

                    <li role="presentation" class="nav-item"><a class="nav-link pointer" href="#Section2" role="tab"
                            data-toggle="tab">{{__('app.Completed_Requests')}}
                            <span class="badge badge-transparent total_close_complaint"></span></a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div class="tab  " role="tabpanel">
              
                <div class="tab-content tabs" id="home">
                    <div role="tabpanel" class="tab-pane active" id="Section1">
                        <table class="table table-striped w-100" id="table-pending">
                            <thead>
                                <tr>
                                    <th> {{ __('app.User_Image') }}</th>
                                    <th> {{ __('app.User') }}</th>
                                    <th> {{ __('app.Request_ID') }}</th>
                                    <th> {{ __('app.Coin_Amount') }}</th>
                                    <th> {{ __('app.Payable_Amount') }}</th>
                                    <th> {{ __('app.Payment_Gateway') }}</th>
                                    <th width="200px" style="text-align: right;"> {{ __('app.Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="Section2">
                        <table class="table table-striped w-100" width="100%" id="table-completed">
                            <thead>
                                <tr>
                                    <th> {{ __('app.User_Image') }}</th>
                                    <th> {{ __('app.User') }}</th>
                                    <th> {{ __('app.Request_ID') }}</th>
                                    <th> {{ __('app.Coin_Amount') }}</th>
                                    <th> {{ __('app.Amount_Paid') }}</th>
                                    <th> {{ __('app.Payment_Gateway') }}</th>
                                    <th width="200px" style="text-align: right;"> {{ __('app.Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div class="modal fade" id="viewRequest" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">

                    <h4 id="request-id">{{ __('app.View_Redeem_Requests') }}</h4>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" class="add_category" id="completeForm"
                        autocomplete="off">
                        @csrf

                        <input type="hidden" class="form-control" id="editId" name="id" value="">

                            <div class="d-flex align-items-center">
                                <img id="user-img" class="mb-2 rounded-circle" src="http://placehold.jp/150x150.png" width="70" height="70">
                                <h5 id="user-fullname" class="m-2 "></h5>
                            </div>


                        <div class="form-group">
                            <label> {{ __('app.Coin_Amount') }}</label>
                            <input id="coin_amount" type="text" name="coin_amount" class="form-control" required readonly>
                        </div>

                        <div class="form-group">
                            <label> {{ __('app.Amount_Paid') }}</label>
                            <input id="amount_paid" type="text" name="amount_paid" class="form-control" required readonly>
                        </div>

                        <div class="form-group">
                            <label> {{ __('app.Payment_Gateway') }}</label>
                            <input id="payment_gateway" type="text" name="payment_gateway" class="form-control" required readonly>
                        </div>


                        <!-- Bank Transfer Details Section -->
                        <div class="form-group">
                            <label><strong>Bank Transfer Details</strong></label>
                            <div class="card border-light">
                                <div class="card-body p-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label><strong>Account Holder Name:</strong></label>
                                            <input id="account_holder_name" type="text" class="form-control mb-2" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label><strong>Bank Name:</strong></label>
                                            <input id="bank_name" type="text" class="form-control mb-2" readonly>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label><strong>Account Number:</strong></label>
                                            <input id="account_number" type="text" class="form-control mb-2" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legacy Account Details (for old records) -->
                        <div class="form-group" id="legacy_account_details" style="display: none;">
                            <label> {{ __('app.Account_details') }}</label>
                            <textarea id="account_details" type="text" name="account_details" class="form-control" required readonly></textarea>
                        </div>

                        <div id="div-submit" class="form-group d-none">
                            <input class="btn btn-success mr-1" type="submit" value=" {{ __('app.Complete') }}">
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>




@endsection
