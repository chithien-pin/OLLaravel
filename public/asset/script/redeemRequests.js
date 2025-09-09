$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".redeemrequestsSideA").addClass("activeLi");

    $("#table-pending").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        autoWidth: false, // Disable auto width calculation
        responsive: false, // Disable responsive feature that might cause overflow
        columnDefs: [
            {
                targets: [0, 1, 2],
                orderable: false,
            },
            {
                targets: 5, // Payment Gateway column (0-indexed)
                width: "160px",
                className: "text-center",
            },
            {
                targets: 6, // Action column
                width: "100px",
                className: "text-center",
            },
        ],
        ajax: {
            url: `${domainUrl}fetchPendingRedeems`,
            data: function (data) {},
        },
        drawCallback: function() {
            // Initialize Bootstrap tooltips after table redraw
            $('[data-toggle="tooltip"]').tooltip();
        }
    });

    $("#table-completed").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        autoWidth: false, // Disable auto width calculation
        responsive: false, // Disable responsive feature that might cause overflow
        columnDefs: [
            {
                targets: [0, 1, 2],
                orderable: false,
            },
            {
                targets: 5, // Payment Gateway column (0-indexed)
                width: "160px",
                className: "text-center",
            },
            {
                targets: 6, // Action column
                width: "100px",
                className: "text-center",
            },
        ],
        ajax: {
            url: `${domainUrl}fetchCompletedRedeems`,
            data: function (data) {},
        },
        drawCallback: function() {
            // Initialize Bootstrap tooltips after table redraw
            $('[data-toggle="tooltip"]').tooltip();
        }
    });


    $("#table-pending").on("click", ".delete", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: app.sure,
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        console.log(true);
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}deleteRedeemRequest`,
                            dataType: "json",
                            data: {
                                redeem_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    iziToast.error({
                                        title: app.Error,
                                        message: response.message || 'Failed to delete redeem request',
                                        position: "topRight",
                                    });
                                } else if (response.status == true) {
                                    iziToast.success({
                                        title: app.Success,
                                        message: response.message || 'Redeem request deleted successfully!',
                                        position: "topRight",
                                    });
                                    $("#table-pending").DataTable().ajax.reload(null, false);
                                }
                            },
                        });
                    }
                }
            });
        } else {
            iziToast.show({
                title: `${app.Error}!`,
                message: app.tester,
                color: app.redToast,
                position: app.toastPosition,
                transitionIn: app.transitionInAction,
                transitionOut: app.transitionOutAction,
                timeout: app.timeout,
                animateInside: false,
                iconUrl: app.cancleIcon,
            });
        }
    });

    
    $("#table-completed").on("click", ".delete", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: app.sure,
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        console.log(true);
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}deleteRedeemRequest`,
                            dataType: "json",
                            data: {
                                redeem_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    iziToast.error({
                                        title: app.Error,
                                        message: response.message || 'Failed to delete redeem request',
                                        position: "topRight",
                                    });
                                } else if (response.status == true) {
                                    iziToast.success({
                                        title: app.Success,
                                        message: response.message || 'Redeem request deleted successfully!',
                                        position: "topRight",
                                    });
                                    $("#table-completed")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                }
                            },
                        });
                    }
                }
            });
        } else {
            iziToast.show({
                title: `${app.Error}!`,
                message: app.tester,
                color: app.redToast,
                position: app.toastPosition,
                transitionIn: app.transitionInAction,
                transitionOut: app.transitionOutAction,
                timeout: app.timeout,
                animateInside: false,
                iconUrl: app.cancleIcon,
            });
        }
    });
   

    $("#table-completed").on("click",".view-request",function(event) {
        event.preventDefault();

        var  id = $(this).attr('rel');

        // $('#editId').val($(this).attr('rel'));

        var url =  `${domainUrl}getRedeemById`+"/"+id;

        $.getJSON(url).done(function(data) {


           if(data.user.image == null){
            var image = 'http://placehold.jp/150x150.png';
           }else{
               var image = `${sourceUrl}`+data.user.image;
           }

            $('#user-img').attr('src', image);
            $('#user-fullname').text(data.user.fullname);
            $('#request-id').text(data.request_id);
            $('#coin_amount').val(data.coin_amount);
            $('#amount_paid').val(data.amount_paid);
            $('#payment_gateway').val(data.payment_gateway);

            // Show structured bank transfer data or legacy account details
            if (data.has_structured_data) {
                $('#account_holder_name').val(data.account_holder_name);
                $('#bank_name').val(data.bank_name);
                $('#account_number').val(data.account_number);
                $('#legacy_account_details').hide();
            } else {
                $('#account_details').val(data.account_details);
                $('#legacy_account_details').show();
            }

            $('#amount_paid').attr("readonly", true);
            $('#div-submit').addClass('d-none');


        });
        $('#viewRequest').modal('show');
    });

    $("#table-pending").on("click",".complete-redeem",function(event) {
        event.preventDefault();

        var  id = $(this).attr('rel');

        $('#editId').val($(this).attr('rel'));

        var url =  `${domainUrl}getRedeemById`+"/"+id;

        $.getJSON(url).done(function(data) {


           if(data.user.image == null){
            var image = 'http://placehold.jp/150x150.png';
           }else{
               var image = `${sourceUrl}`+data.user.image;
           }

            $('#user-img').attr('src', image);
            $('#user-fullname').text(data.user.fullname);
            $('#request-id').text(data.request_id);
            $('#coin_amount').val(data.coin_amount);
            $('#payment_gateway').val(data.payment_gateway);

            // Auto-fill amount paid based on coin amount and rate
            if (data.expected_amount) {
                $('#amount_paid').val(data.expected_amount.toFixed(2));
            } else {
                // Fallback calculation
                var expectedAmount = (data.coin_amount * 0.006).toFixed(2);
                $('#amount_paid').val(expectedAmount);
            }

            // Show structured bank transfer data or legacy account details
            if (data.has_structured_data) {
                $('#account_holder_name').val(data.account_holder_name);
                $('#bank_name').val(data.bank_name);
                $('#account_number').val(data.account_number);
                $('#legacy_account_details').hide();
            } else {
                $('#account_details').val(data.account_details);
                $('#legacy_account_details').show();
            }

            $('#amount_paid').attr("readonly", false);
            $('#div-submit').removeClass('d-none');

        });
        $('#viewRequest').modal('show');
    });

    // Handle Info button click for both tables
    $("#table-pending, #table-completed").on("click", ".info-payment", function(event) {
        event.preventDefault();
        
        var id = $(this).attr('rel');
        var url = `${domainUrl}getRedeemById/` + id;
        
        $.getJSON(url).done(function(data) {
            var infoContent = '';
            
            // Check if we have structured data
            if (data.has_structured_data) {
                infoContent = `
                    <div style="text-align: left;">
                        <strong>Bank Transfer Information:</strong><br><br>
                        <strong>Account Holder:</strong> ${data.account_holder_name}<br>
                        <strong>Bank Name:</strong> ${data.bank_name}<br>
                        <strong>Account Number:</strong> ${data.account_number}
                    </div>
                `;
            } else if (data.account_details) {
                infoContent = `
                    <div style="text-align: left;">
                        <strong>Bank Transfer Information:</strong><br><br>
                        ${data.account_details.replace(/\n/g, '<br>')}
                    </div>
                `;
            } else {
                infoContent = '<div style="text-align: left;">No bank transfer information available.</div>';
            }
            
            // Show info in a simple alert or modal
            swal({
                title: 'Payment Information',
                content: {
                    element: 'div',
                    attributes: {
                        innerHTML: infoContent
                    }
                },
                button: 'Close'
            });
        });
    });

    $("#completeForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();

        if (user_type == "1") {
            var formdata = new FormData($("#completeForm")[0]);

            $.ajax({
                url: `${domainUrl}completeRedeem`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    console.log(response);
                    $("#table-pending").DataTable().ajax.reload(null, false);
                    $("#table-completed").DataTable().ajax.reload(null, false);

                    $(".loader").hide();
                    $("#viewRequest").modal("hide");


                    if (response.status == false) {
                        iziToast.error({
                            title: app.Error,
                            message: response.message,
                            position: "topRight",
                        });
                    } else {
                        // Show success notification for completed redeem
                        iziToast.success({
                            title: app.Success,
                            message: response.message || 'Redeem request completed successfully!',
                            position: "topRight",
                        });
                    }
                },
                error: function (err) {
                    console.log(err);
                    $(".loader").hide();
                    iziToast.error({
                        title: app.Error,
                        message: 'An error occurred while completing the request',
                        position: "topRight",
                    });
                },
            });
        } else {
            iziToast.error({
                title: app.Error,
                message: app.tester,
                position: "topRight",
            });
        }
    });




});
