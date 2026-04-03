$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".adminsSideA").addClass("activeLi");

    $("#AdminsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        autoWidth: true,
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchAdminUsers`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    // Remove Admin Handler
    $(document).on("click", ".removeAdmin", function (e) {
        e.preventDefault();
        var id = $(this).attr("data-id");
        var name = $(this).attr("data-name");
        swal({
            title: "Remove admin role from " + name + "?",
            text: "This user will lose admin privileges.",
            icon: "warning",
            buttons: ["Cancel", "Yes, Remove"],
            dangerMode: true,
        }).then((confirmed) => {
            if (confirmed) {
                $.ajax({
                    type: "POST",
                    url: `${domainUrl}toggleAdminFromAdmin`,
                    dataType: "json",
                    data: { user_id: id },
                    success: function (response) {
                        if (response.status) {
                            iziToast.success({ title: "Success!", message: response.message, position: "topRight" });
                            $("#AdminsTable").DataTable().ajax.reload(null, false);
                        } else {
                            iziToast.error({ title: "Error", message: response.message, position: "topRight" });
                        }
                    },
                    error: function () {
                        iziToast.error({ title: "Error", message: "Failed to update admin role", position: "topRight" });
                    },
                });
            }
        });
    });
});
