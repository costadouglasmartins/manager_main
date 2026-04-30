document.addEventListener("DOMContentLoaded", () => {
  const sheet = document.querySelector(".financial-sheet-table");

  if (!sheet) {
    return;
  }

  sheet.addEventListener("click", (event) => {
    const cell = event.target.closest("td, th");

    if (!cell || !sheet.contains(cell)) {
      return;
    }

    sheet.querySelectorAll(".financial-sheet-cell-selected").forEach((selectedCell) => {
      selectedCell.classList.remove("financial-sheet-cell-selected");
    });
    cell.classList.add("financial-sheet-cell-selected");
  });
});
