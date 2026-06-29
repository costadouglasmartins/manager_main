document.addEventListener("DOMContentLoaded", () => {
  const participant = document.getElementById("participant");

  if (participant) {
    participant.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        participant.value = "";
      }
    });
  }

  setupActionModal("reopenModal", "reopenForm", "reopenRecordId", "reopenRecordName");
  setupActionModal("deleteModal", "deleteForm", "deleteRecordId", "deleteRecordName");

  function setupActionModal(modalId, formId, recordIdInputId, recordNameId) {
    const modal = document.getElementById(modalId);

    if (!modal) {
      return;
    }

    modal.addEventListener("show.bs.modal", (event) => {
      const trigger = event.relatedTarget;
      const form = document.getElementById(formId);
      const recordIdInput = document.getElementById(recordIdInputId);
      const recordName = document.getElementById(recordNameId);

      if (!trigger || !form || !recordIdInput || !recordName) {
        return;
      }

      const recordId = trigger.dataset.id || "";
      const baseUrl = (form.dataset.actionBaseUrl || "").replace(/\/$/, "");

      recordIdInput.value = recordId;
      recordName.textContent = trigger.dataset.name || recordId;
      form.action = `${baseUrl}/${encodeURIComponent(recordId)}`;
    });
  }
});
