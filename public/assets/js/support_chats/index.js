document.addEventListener("DOMContentLoaded", () => {
  const participant = document.getElementById("participant");

  if (!participant) {
    return;
  }

  participant.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      participant.value = "";
    }
  });
});
