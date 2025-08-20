$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".usersSideA").addClass("activeLi");

    var id = $("#userId").val();
    console.log(id);

    // Load user role on page load
    loadUserRole(id);

    $("#btnAddImage").on("click", function (event) {
        event.preventDefault();
        $("#addImageModal").modal("show");
    });

    // Image Add
    $("#addForm").submit(function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var formdata = new FormData($("#addForm")[0]);

            console.log(formdata);
            $.ajax({
                url: `${domainUrl}addUserImage`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    if (response.status == true) {
                        console.log(response.status);
                        window.location.href = "";
                    } else if (response.status == false) {
                        console.log(err);
                    }
                },
                error: function (err) {
                    console.log(err);
                },
            });
        } else {
            iziToast.error({
                title: "Tester Login",
                message: "you are tester",
                position: "topRight",
                timeOut: 4000,
            });
        }
    });

    $(document).on("click", ".btnRemove", function (event) {
        event.preventDefault();
        var imgId = $(this).data("imgid");
        console.log(imgId);
        swal({
            title: app.sure,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                if (user_type == "1") {
                    var url = `${domainUrl}deleteUserImage` + "/" + imgId;
                    $.getJSON(url).done(function (response) {
                        if (response.status == true) {
                            console.log(response.status);
                            location.reload();
                        } else if (response.status == false) {
                            console.log(response);
                            iziToast.error({
                                title: `${app.Error}!`,
                                message: response.message,
                                position: "topRight",
                            });
                        }
                    });
                } else {
                    iziToast.error({
                        title: `${app.Error}!`,
                        message: app.tester,
                        position: "topRight",
                    });
                }
            }
        });
    });

    // form data update
    $("#userUpdate").submit(function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var formdata = new FormData($("#userUpdate")[0]);

            console.log(formdata);
            $.ajax({
                url: `${domainUrl}updateUser`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    if (response.status == true) {
                        console.log(response.status);
                        window.location.href = "";
                    } else if (response.status == false) {
                        console.log(err);
                    }
                },
                error: function (err) {
                    console.log(err);
                },
            });
        } else {
            iziToast.error({
                title: "Tester Login",
                message: "you are tester",
                position: "topRight",
                timeOut: 4000,
            });
        }
    });

    $(document).on("click", ".allow-live", function (e) {
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
                            url: `${domainUrl}allowLiveToUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.thisUserisAllowedToGoLive,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#FakeUsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#StreamersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#reloadContent").load(
                                        location.href + " #reloadContent>*",
                                        ""
                                    );
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

    $(document).on("click", ".restrict-live", function (e) {
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
                            url: `${domainUrl}restrictLiveToUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.restrictLiveAccessToUser,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#FakeUsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#StreamersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#reloadContent").load(
                                        location.href + " #reloadContent>*",
                                        ""
                                    );
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

    $(document).on("click", ".block", function (e) {
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
                            url: `${domainUrl}blockUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.thisUserHasBeenBlocked,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#FakeUsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#StreamersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#reloadContent").load(
                                        location.href + " #reloadContent>*",
                                        ""
                                    );
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

    $(document).on("click", ".unblock", function (e) {
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
                            url: `${domainUrl}unblockUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.thisUserHasBeenUnblocked,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#FakeUsersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#StreamersTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    $("#reloadContent").load(
                                        location.href + " #reloadContent>*",
                                        ""
                                    );
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

    $("#userPostTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}userPostList`,
            data: {
                userId: id,
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#userPostTable").on("click", ".deletePost", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: "Are you sure?",
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}deletePostFromUserPostTable`,
                            dataType: "json",
                            data: {
                                post_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.postDeleteSuccessfully,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#userPostTable")
                                        .DataTable()
                                        .ajax.reload(null, false);
                                    console.log(response.message);
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

    $(document).on("click", ".viewStory", function (e) {
        e.preventDefault();
        var story = $(this).data("image");

        $("#story_content").attr("src", story);
        $("#viewStoryModal").modal("show");
    });

    $(document).on("click", ".viewStoryVideo", function (e) {
        e.preventDefault();
        var story = $(this).data("image");

        $("#story_content_video").attr("src", story);
        $("#viewStoryVideoModal").modal("show");
    });

    $("#userStoryTable").dataTable({
        process: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}userStoryList`,
            data: {
                user_id: id,
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#userStoryTable").on("click", ".deleteStory", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: "Are you sure?",
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}deleteStoryFromAdmin`,
                            dataType: "json",
                            data: {
                                story_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: "Deleted",
                                        message: "Story Delete Succesfully",
                                        color: "green",
                                        position: "bottomCenter",
                                        transitionIn: "fadeInUp",
                                        transitionOut: "fadeOutDown",
                                        timeout: 3000,
                                        animateInside: false,
                                        iconUrl: `${domainUrl}asset/img/check-circle.svg`,
                                    });
                                    $("#userStoryTable")
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
                title: "Oops",
                message: "You are tester",
                color: "red",
                position: toastPosition,
                transitionIn: "fadeInUp",
                transitionOut: "fadeOutDown",
                timeout: 3000,
                animateInside: false,
                iconUrl: `${domainUrl}asset/img/x.svg`,
            });
        }
    });

        $(document).on("click", ".deleteUser", function (e) {
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
                                url: `${domainUrl}deleteUserFromAdmin`,
                                dataType: "json",
                                data: {
                                    user_id: id,
                                },
                                success: function (response) {
                                    if (response.status == false) {
                                        console.log(response.message);
                                    } else if (response.status == true) {
                                        window.location.href = `${domainUrl}users`;
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

    // Role Management Functions
    function loadUserRole(userId) {
        $.ajax({
            url: `${domainUrl}getUserRoleHistory`,
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                user_id: userId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (response) {
                if (response.status) {
                    const currentRole = response.current_role;
                    let roleText = currentRole.role_type.charAt(0).toUpperCase() + currentRole.role_type.slice(1);
                    
                    if (currentRole.is_vip && currentRole.expires_at) {
                        const expiryDate = new Date(currentRole.expires_at);
                        const daysRemaining = currentRole.days_remaining;
                        roleText += ` (${daysRemaining} days remaining)`;
                    }
                    
                    $("#currentRoleText").text(roleText);
                    
                    // Update button style based on role
                    const roleButton = $("#roleManagementBtn");
                    roleButton.removeClass("btn-success btn-warning btn-info btn-primary");
                    if (currentRole.is_vip) {
                        roleButton.addClass("btn-warning");
                    } else {
                        roleButton.addClass("btn-success");
                    }

                    // Update modal display
                    updateModalRoleDisplay(currentRole);
                } else {
                    $("#currentRoleText").text("Error loading role");
                }
            },
            error: function (xhr, status, error) {
                console.error("Error loading role:", error);
                $("#currentRoleText").text("Normal");
            }
        });
    }

    function updateModalRoleDisplay(currentRole) {
        const modalCurrentRole = $("#modalCurrentRole");
        const modalRoleExpiry = $("#modalRoleExpiry");
        
        let roleText = currentRole.role_type.charAt(0).toUpperCase() + currentRole.role_type.slice(1);
        
        modalCurrentRole.removeClass("badge-success badge-warning badge-danger");
        if (currentRole.is_vip) {
            modalCurrentRole.addClass("badge-warning").text(roleText);
            if (currentRole.expires_at) {
                const expiryDate = new Date(currentRole.expires_at);
                const daysRemaining = currentRole.days_remaining;
                modalRoleExpiry.text(`Expires in ${daysRemaining} days (${expiryDate.toLocaleDateString()})`);
            }
        } else {
            modalCurrentRole.addClass("badge-success").text(roleText);
            modalRoleExpiry.text("");
        }
    }

    // Open Role Management Modal
    $(document).on("click", "#roleManagementBtn", function(e) {
        e.preventDefault();
        updateModalOptionsBasedOnCurrentRole();
        $("#roleManagementModal").modal("show");
    });

    function updateModalOptionsBasedOnCurrentRole() {
        const roleButton = $("#roleManagementBtn");
        const isCurrentlyVIP = roleButton.hasClass("btn-warning");
        const revokeOption = $("#revokeRole").closest(".role-option-item");
        
        if (isCurrentlyVIP) {
            // If user is VIP, show extend options and revoke option
            $("#vip1MonthText").text("Extend VIP (1 Month)");
            $("#vip1YearText").text("Extend VIP (1 Year)");
            $("#vip20SecondsText").text("Extend VIP (20 Seconds) - TEST");
            revokeOption.show();
        } else {
            // If user is Normal, show grant options only (no revoke needed)
            $("#vip1MonthText").text("Grant VIP (1 Month)");
            $("#vip1YearText").text("Grant VIP (1 Year)");
            $("#vip20SecondsText").text("Grant VIP (20 Seconds) - TEST");
            revokeOption.hide();
        }
    }

    // Handle role option selection with smooth visual feedback
    $(document).on("change", "input[name='roleOption']", function() {
        // Update visual state for selected option
        $(".role-option-item").removeClass("selected");
        $(this).closest(".role-option-item").addClass("selected");
    });

    // Apply Role Changes
    $(document).on("click", "#applyRoleBtn", function(e) {
        e.preventDefault();
        
        const selectedOption = $("input[name='roleOption']:checked").val();
        const userId = $("#userId").val();
        
        if (!selectedOption) {
            iziToast.error({
                title: "Error",
                message: "Please select a role option",
                position: "topRight",
                timeout: 4000,
            });
            return;
        }
        
        if (user_type == 1) {
            // Show loading state
            $("#applyRoleBtn").prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Applying...');
            
            if (selectedOption === "revoke") {
                // Handle revoke role
                $.ajax({
                    url: `${domainUrl}revokeUserRole`,
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        user_id: userId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: "json",
                    success: function (response) {
                        handleRoleChangeResponse(response, userId);
                    },
                    error: function (xhr, status, error) {
                        console.error("Error revoking role:", error);
                        handleRoleChangeError("Failed to revoke role");
                    }
                });
            } else {
                // Handle assign VIP role
                let roleType = "vip";
                let duration;
                
                if (selectedOption === "vip_1_month") {
                    duration = "1_month";
                } else if (selectedOption === "vip_1_year") {
                    duration = "1_year";
                }
                
                $.ajax({
                    url: `${domainUrl}assignUserRole`,
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        user_id: userId,
                        role_type: roleType,
                        duration: duration,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: "json",
                    success: function (response) {
                        handleRoleChangeResponse(response, userId);
                    },
                    error: function (xhr, status, error) {
                        console.error("Error assigning role:", error);
                        handleRoleChangeError("Failed to assign role");
                    }
                });
            }
        } else {
            iziToast.error({
                title: "Tester Login",
                message: "You are tester",
                position: "topRight",
                timeout: 4000,
            });
        }
    });

    function handleRoleChangeResponse(response, userId) {
        // Reset button state
        $("#applyRoleBtn").prop("disabled", false).html('<i class="fas fa-check mr-1"></i>Apply Changes');
        
        if (response.status) {
            iziToast.success({
                title: "Success",
                message: response.message,
                position: "topRight",
                timeout: 4000,
            });
            
            // Close modal and reload role display
            $("#roleManagementModal").modal("hide");
            loadUserRole(userId);
            
            // Reset radio buttons and visual state
            $("input[name='roleOption']").prop("checked", false);
            $(".role-option-item").removeClass("selected");
        } else {
            iziToast.error({
                title: "Error",
                message: response.message,
                position: "topRight",
                timeout: 4000,
            });
        }
    }

    function handleRoleChangeError(message) {
        // Reset button state
        $("#applyRoleBtn").prop("disabled", false).html('<i class="fas fa-check mr-1"></i>Apply Changes');
        
        iziToast.error({
            title: "Error",
            message: message,
            position: "topRight",
            timeout: 4000,
        });
    }
     
});
