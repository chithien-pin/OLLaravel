@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/users.js') }}?v=4"></script>
@endsection

@section('content')
    <div class="card">
        {{-- Header hidden --}}

        <div class="card-body">
            <table class="table table-striped" style="width:100%;" id="UsersTable">
                <thead>
                    <tr>
                        <th style="width:60px;">Image</th>
                        <th style="width:12%;">Username</th>
                        <th>Email</th>
                        <th style="width:15%;">Full Name</th>
                        <th style="width:18%;">Joined</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    {{-- Add coins Modal --}}
    <div class="modal fade" id="addCoinsModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">

                <h5>{{ __('Add Coins') }}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <form action="" method="post" enctype="multipart/form-data" class="add_category" id="addCoinsForm"
                    autocomplete="off">
                    @csrf

                    <input type="hidden" name="id" id="userId" value="">

                    <div class="form-group">
                        <label for="coins">Coins</label>
                        <input required type="number" class="form-control" id="coins" name="coins"
                            placeholder="Enter Coin Amount">
                    </div>

                    <div class="form-group">
                        <input class="btn btn-primary mr-1" type="submit" value=" {{ __('app.Submit') }}">
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>
@endsection
