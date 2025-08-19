document.getElementById("columnSettingsBtn").addEventListener("click", () => {
    const panel = document.getElementById("columnSettings")
    panel.classList.toggle("d-none")
})


document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("messagesTable");
    const checkboxes = document.querySelectorAll("#columnCheckboxes input[type='checkbox']");

    // Apply saved preferences from localStorage
    checkboxes.forEach(cb => {
        const colKey = cb.id.replace("col-", ""); // e.g. col-user → user
        const saved = localStorage.getItem("col-" + colKey);

        if (saved !== null) {
            cb.checked = saved === "true"; // restore checked state
        }

        toggleColumn(colKey, cb.checked); // apply visibility
    });

    // Listen for checkbox changes
    checkboxes.forEach(cb => {
        cb.addEventListener("change", function () {
            const colKey = this.id.replace("col-", "");
            toggleColumn(colKey, this.checked);

            // Save preference
            localStorage.setItem("col-" + colKey, this.checked);
        });
    });

    function toggleColumn(colKey, show) {
        const selector = `[data-col='${colKey}']`; // header
        const header = table.querySelectorAll("thead " + selector);
        const bodyCells = table.querySelectorAll("tbody td:nth-child(" + getColumnIndex(colKey) + ")");

        // Toggle header
        header.forEach(el => el.classList.toggle("d-none", !show));

        // Toggle all body cells in that column
        bodyCells.forEach(el => el.classList.toggle("d-none", !show));
    }

    // Helper: find index of column (1-based for nth-child)
    function getColumnIndex(colKey) {
        const headers = table.querySelectorAll("thead th");
        for (let i = 0; i < headers.length; i++) {
            if (headers[i].dataset.col === colKey) {
                return i + 1; // nth-child is 1-based
            }
        }
        return -1;
    }
});


document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("messagesTable");
    const rows = table.querySelectorAll("tbody tr");

    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const categoryFilter = document.getElementById("categoryFilter");

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();
        const categoryValue = categoryFilter.value.toLowerCase();

        rows.forEach(row => {
            const cells = row.querySelectorAll("td");

            const user     = cells[0].innerText.toLowerCase();   // user
            const phone    = cells[1].innerText.toLowerCase();   // phone
            const waId     = cells[2].innerText.toLowerCase();   // wa_id
            const cohort   = cells[3].innerText.toLowerCase();   // cohort
            const message  = cells[4].innerText.toLowerCase();   // message
            const status   = cells[6].innerText.toLowerCase();   // status
            const category = cells[17].innerText.toLowerCase();  // pricing_category

            // Search condition (matches user/phone/waId/message)
            const matchesSearch = !searchText || (
                user.includes(searchText) ||
                phone.includes(searchText) ||
                waId.includes(searchText) ||
                cohort.includes(searchText) ||
                message.includes(searchText)
            );

            // Status condition
            const matchesStatus = !statusValue || status.includes(statusValue);

            // Category condition
            const matchesCategory = !categoryValue || category.includes(categoryValue);

            // Show or hide row
            if (matchesSearch && matchesStatus && matchesCategory) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // Bind events
    searchInput.addEventListener("keyup", filterTable);
    statusFilter.addEventListener("change", filterTable);
    categoryFilter.addEventListener("change", filterTable);
});
