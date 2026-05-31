$(document).ready(function () {
  $("#sampleTable").DataTable({
    responsive: true,
    dom: "Bfrtilp",
    lengthMenu: [5, 15, 25, 50, 75, 100],
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="bi bi-file-earmark-excel-fill"></i>Excel',
        titleAttr: "Exportar a Excel",
        className: "btn btn-success me-2",
      },
      {
        extend: "pdfHtml5",
        text: '<i class="bi bi-file-earmark-pdf-fill"></i>PDF',
        titleAttr: "Exportar a PDF",
        className: "btn btn-danger me-2",
      },
      {
        extend: "print",
        text: '<i class="bi bi-printer"></i> IMPRIMIR',
        titleAttr: "Imprimir",
        className: "btn btn-info",
      },
    ],
    language: {
      lengthMenu: "Mostrar _MENU_ entradas <br><br>",
      search: "_INPUT_",
      searchPlaceholder: "Buscar registros",
      info: "<p style='position: absolute; right: 25px;'>Mostrando _START_ a _END_ de _TOTAL_ entradas</p>",
      paginate: {
        first: "«",
        last: "»",
        next: "›",
        previous: "‹",
      },
    },
  });
});
