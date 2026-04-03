$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".bannedSideA").addClass("activeLi");

    $("#BannedTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        autoWidth: true,
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchBannedUsers`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    // Unban User Handler
    $(document).on("click", ".unbanUser", function (e) {
        e.preventDefault();
        var id = $(this).attr("data-id");
        var name = $(this).attr("data-name");
        swal({
            title: "Unban " + name + "?",
            text: "This will restore their account and posts.",
            icon: "info",
            buttons: ["Cancel", "Yes, Unban"],
        }).then((confirmed) => {
            if (confirmed) {
                $(".loader").show();
                $.ajax({
                    type: "POST",
                    url: `${domainUrl}unbanUserFromAdmin`,
                    dataType: "json",
                    data: { user_id: id },
                    success: function (response) {
                        $(".loader").hide();
                        if (response.status) {
                            iziToast.success({ title: "Unbanned!", message: response.message, position: "topRight" });
                            $("#BannedTable").DataTable().ajax.reload(null, false);
                        } else {
                            iziToast.error({ title: "Error", message: response.message, position: "topRight" });
                        }
                    },
                    error: function () {
                        $(".loader").hide();
                        iziToast.error({ title: "Error", message: "Failed to unban user", position: "topRight" });
                    },
                });
            }
        });
    });
});
