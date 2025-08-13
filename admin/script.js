const mockData = [
  {
    id: 1,
    user: "Admin",
    phone: "9478718456",
    waId: "9478718456",
    client: "test",
    message: "payment_reminder_1_week_after_class_started",
    messageId: "wamid.HBgLOTQ3ODc1...",
    status: "sent",
    sentAt: "2025 Jul 14 | 12:11 pm",
    sent: "2025 Jul 14 | 12:11 pm",
    failed: "—",
    delivered: "Jun 26 | 1:54 pm",
    read: "Jun 26 | 1:54 pm",
    conversationId: "a2548f6e8b8b0b386...",
    convExp: "Jun 19 | 5:41 pm",
    origin: "marketing",
    billable: "Yes",
    model: "PMP",
    category: "marketing",
    cost: "0.0730",
    errCode: "—",
    errTitle: "—",
    errMessage: "—",
    errDetails: "—",
    reply: "—",
    replyAt: "—",
  },
  {
    id: 2,
    user: "Admin",
    phone: "9478718456",
    waId: "9478718456",
    client: "test",
    message: "new_ai_chatbot_rollout_support_channel",
    messageId: "wamid.HBgLOTQ3ODc2...",
    status: "delivered",
    sentAt: "2025 Jul 14 | 7:40 pm",
    sent: "2025 Jul 14 | 7:40 pm",
    failed: "—",
    delivered: "Jun 26 | 1:54 pm",
    read: "Jun 26 | 1:54 pm",
    conversationId: "a2548f6e8b8b0b386...",
    convExp: "Jun 19 | 5:41 pm",
    origin: "support",
    billable: "Yes",
    model: "CBP",
    category: "support",
    cost: "0.0730",
    errCode: "—",
    errTitle: "—",
    errMessage: "—",
    errDetails: "—",
    reply: "—",
    replyAt: "—",
  },
  {
    id: 3,
    user: "Admin",
    phone: "9478718456",
    waId: "9478718456",
    client: "test",
    message: "orientation_session_timeout",
    messageId: "wamid.HBgLOTQ3ODc3...",
    status: "failed",
    sentAt: "2025 Jul 14 | 7:40 pm",
    sent: "2025 Jul 14 | 7:40 pm",
    failed: "2025 Jul 14 | 7:41 pm",
    delivered: "—",
    read: "—",
    conversationId: "a2548f6e8b8b0b386...",
    convExp: "Jun 19 | 5:41 pm",
    origin: "system",
    billable: "No",
    model: "—",
    category: "notification",
    cost: "0.0000",
    errCode: "131000",
    errTitle: "Message undeliverable",
    errMessage: "User number is not a WhatsApp number",
    errDetails: "The phone number is not registered with WhatsApp",
    reply: "—",
    replyAt: "—",
  },
  {
    id: 4,
    user: "Admin",
    phone: "9478718456",
    waId: "9478718456",
    client: "test",
    message: "class_rescheduled",
    messageId: "wamid.HBgLOTQ3ODc4...",
    status: "read",
    sentAt: "2025 Jul 14 | 5:15 pm",
    sent: "2025 Jul 14 | 5:15 pm",
    failed: "—",
    delivered: "Jun 26 | 1:54 pm",
    read: "Jun 26 | 1:54 pm",
    conversationId: "a2548f6e8b8b0b386...",
    convExp: "Jun 19 | 5:41 pm",
    origin: "notification",
    billable: "Yes",
    model: "PMP",
    category: "notification",
    cost: "0.0730",
    errCode: "—",
    errTitle: "—",
    errMessage: "—",
    errDetails: "—",
    reply: "Thanks for the update!",
    replyAt: "Jun 26 | 2:15 pm",
  },
  {
    id: 5,
    user: "Admin",
    phone: "9478718456",
    waId: "9478718456",
    client: "test",
    message: "activate_contacts",
    messageId: "wamid.HBgLOTQ3ODc5...",
    status: "sent",
    sentAt: "2025 Jul 14 | 3:21 pm",
    sent: "2025 Jul 14 | 3:21 pm",
    failed: "—",
    delivered: "—",
    read: "—",
    conversationId: "a2548f6e8b8b0b386...",
    convExp: "Jun 19 | 5:41 pm",
    origin: "system",
    billable: "Yes",
    model: "CBP",
    category: "system",
    cost: "0.0730",
    errCode: "—",
    errTitle: "—",
    errMessage: "—",
    errDetails: "—",
    reply: "—",
    replyAt: "—",
  },
]

class DashboardApp {
  constructor() {
    this.data = mockData
    this.filteredData = [...this.data]
    this.currentPage = 1
    this.itemsPerPage = 10
    this.expandedRows = new Set()
    this.visibleColumns = {
      user: true,
      phone: true,
      message: true,
      status: true,
      category: true,
      sentAt: true,
      cost: true,
    }
    this.searchTerm = ""
    this.statusFilter = ""
    this.categoryFilter = ""

    this.init()
  }

  init() {
    this.setupEventListeners()
    this.setupColumnSettings()
    this.renderTable()
    this.renderPagination()
  }

  setupEventListeners() {
    // Search functionality
    document.getElementById("searchInput").addEventListener("input", (e) => {
      this.searchTerm = e.target.value
      this.filterData()
    })

    // Filter functionality
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      this.statusFilter = e.target.value
      this.filterData()
    })

    document.getElementById("categoryFilter").addEventListener("change", (e) => {
      this.categoryFilter = e.target.value
      this.filterData()
    })

    // Column settings toggle
    document.getElementById("columnSettingsBtn").addEventListener("click", () => {
      const panel = document.getElementById("columnSettings")
      panel.classList.toggle("d-none")
    })
  }

  setupColumnSettings() {
    const container = document.getElementById("columnCheckboxes")
    const columns = Object.keys(this.visibleColumns)

    columns.forEach((column) => {
      const div = document.createElement("div")
      div.className = "col-md-3 col-sm-6 mb-2"
      div.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="col-${column}" ${this.visibleColumns[column] ? "checked" : ""}>
                    <label class="form-check-label" for="col-${column}">
                        ${this.formatColumnName(column)}
                    </label>
                </div>
            `
      container.appendChild(div)

      // Add event listener
      document.getElementById(`col-${column}`).addEventListener("change", (e) => {
        this.visibleColumns[column] = e.target.checked
        this.renderTable()
      })
    })
  }

  formatColumnName(column) {
    return column.replace(/([A-Z])/g, " $1").replace(/^./, (str) => str.toUpperCase())
  }

  filterData() {
    this.filteredData = this.data.filter((item) => {
      const matchesSearch =
        !this.searchTerm ||
        item.message.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
        item.phone.includes(this.searchTerm) ||
        item.user.toLowerCase().includes(this.searchTerm.toLowerCase())

      const matchesStatus = !this.statusFilter || item.status === this.statusFilter
      const matchesCategory = !this.categoryFilter || item.category === this.categoryFilter

      return matchesSearch && matchesStatus && matchesCategory
    })

    this.currentPage = 1
    this.renderTable()
    this.renderPagination()
  }

  renderTable() {
    this.renderTableHeader()
    this.renderTableBody()
  }

  renderTableHeader() {
    const header = document.getElementById("tableHeader")
    const visibleCols = Object.entries(this.visibleColumns).filter(([_, visible]) => visible)

    header.innerHTML = `
            <th width="50"></th>
            ${visibleCols.map(([col, _]) => `<th>${this.formatColumnName(col)}</th>`).join("")}
            <th>Actions</th>
        `
  }

  renderTableBody() {
    const tbody = document.getElementById("tableBody")
    const startIndex = (this.currentPage - 1) * this.itemsPerPage
    const endIndex = startIndex + this.itemsPerPage
    const pageData = this.filteredData.slice(startIndex, endIndex)

    tbody.innerHTML = ""

    pageData.forEach((item) => {
      // Main row
      const row = document.createElement("tr")
      row.className = "fade-in"
      row.innerHTML = this.renderTableRow(item)
      tbody.appendChild(row)

      // Expandable row
      if (this.expandedRows.has(item.id)) {
        const expandRow = document.createElement("tr")
        expandRow.innerHTML = this.renderExpandableRow(item)
        tbody.appendChild(expandRow)
      }
    })
  }

  renderTableRow(item) {
    const visibleCols = Object.entries(this.visibleColumns).filter(([_, visible]) => visible)
    const colCount = visibleCols.length + 2

    return `
            <td>
                <button class="expand-btn" onclick="app.toggleRowExpansion(${item.id})">
                    <i class="fas fa-chevron-${this.expandedRows.has(item.id) ? "up" : "down"}"></i>
                </button>
            </td>
            ${visibleCols.map(([col, _]) => `<td>${this.renderCellContent(item, col)}</td>`).join("")}
            <td>
                <button class="action-btn" onclick="app.showDetails(${item.id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `
  }

  renderCellContent(item, column) {
    switch (column) {
      case "status":
        return `<span class="status-badge status-${item.status}">${item.status}</span>`
      case "category":
        return `<span class="category-badge category-${item.category}">${item.category}</span>`
      case "message":
        return `<span class="text-truncate d-inline-block" style="max-width: 200px;" title="${item.message}">${item.message}</span>`
      case "phone":
        return `<code class="small">${item.phone}</code>`
      case "cost":
        return `<code class="small">$${item.cost}</code>`
      default:
        return item[column]
    }
  }

  renderExpandableRow(item) {
    const colCount = Object.values(this.visibleColumns).filter(Boolean).length + 2

    return `
            <td colspan="${colCount}">
                <div class="expandable-content">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-id-card me-1"></i>Message ID</h6>
                            <p class="small font-monospace text-break">${item.messageId}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-whatsapp me-1"></i>WhatsApp ID</h6>
                            <p class="small font-monospace">${item.waId}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-user me-1"></i>Client</h6>
                            <p class="small">${item.client}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-check me-1"></i>Delivered</h6>
                            <p class="small">${item.delivered}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-eye me-1"></i>Read</h6>
                            <p class="small">${item.read}</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="fas fa-tag me-1"></i>Origin</h6>
                            <p class="small text-capitalize">${item.origin}</p>
                        </div>
                        ${
                          item.errCode !== "—"
                            ? `
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-1"></i>Error Details</h6>
                                    <p class="mb-1"><strong>Code:</strong> ${item.errCode}</p>
                                    <p class="mb-1"><strong>Title:</strong> ${item.errTitle}</p>
                                    <p class="mb-0"><strong>Message:</strong> ${item.errMessage}</p>
                                </div>
                            </div>
                        `
                            : ""
                        }
                        ${
                          item.reply !== "—"
                            ? `
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <h6 class="alert-heading"><i class="fas fa-reply me-1"></i>Reply</h6>
                                    <p class="mb-1">${item.reply}</p>
                                    <small class="text-muted">Replied at: ${item.replyAt}</small>
                                </div>
                            </div>
                        `
                            : ""
                        }
                    </div>
                </div>
            </td>
        `
  }

  toggleRowExpansion(id) {
    if (this.expandedRows.has(id)) {
      this.expandedRows.delete(id)
    } else {
      this.expandedRows.add(id)
    }
    this.renderTable()
  }

  showDetails(id) {
    const item = this.data.find((d) => d.id === id)
    if (!item) return

    const modalBody = document.getElementById("modalBody")
    modalBody.innerHTML = this.renderModalContent(item)

    const modal = window.bootstrap.Modal.getOrCreateInstance(document.getElementById("messageModal"))
    modal.show()
  }

  renderModalContent(item) {
    return `
            <div class="row g-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>User Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>User:</strong> ${item.user}</p>
                                    <p class="mb-2"><strong>Client:</strong> ${item.client}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Phone:</strong> <code>${item.phone}</code></p>
                                    <p class="mb-2"><strong>WhatsApp ID:</strong> <code>${item.waId}</code></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-comment me-2"></i>Message Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-3"><strong>Message:</strong><br>${item.message}</p>
                            <p class="mb-2"><strong>Message ID:</strong><br><code class="small">${item.messageId}</code></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Status:</strong> <span class="status-badge status-${item.status}">${item.status}</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Category:</strong> <span class="category-badge category-${item.category}">${item.category}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Sent:</strong> ${item.sent}</p>
                                    <p class="mb-2"><strong>Delivered:</strong> ${item.delivered}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Read:</strong> ${item.read}</p>
                                    ${item.failed !== "—" ? `<p class="mb-2 text-danger"><strong>Failed:</strong> ${item.failed}</p>` : ""}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Technical Details</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Conversation ID:</strong><br><code class="small">${item.conversationId}</code></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Origin:</strong> ${item.origin}</p>
                                    <p class="mb-2"><strong>Model:</strong> ${item.model}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Billable:</strong> ${item.billable}</p>
                                    <p class="mb-2"><strong>Cost:</strong> <code>$${item.cost}</code></p>
                                </div>
                            </div>
                            <p class="mb-0"><strong>Conv. Expiry:</strong> ${item.convExp}</p>
                        </div>
                    </div>
                </div>

                ${
                  item.errCode !== "—"
                    ? `
                    <div class="col-12">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error Details</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Error Code:</strong> <code class="text-danger">${item.errCode}</code></p>
                                <p class="mb-2"><strong>Error Title:</strong> ${item.errTitle}</p>
                                <p class="mb-2"><strong>Error Message:</strong> ${item.errMessage}</p>
                                <p class="mb-0"><strong>Error Details:</strong> ${item.errDetails}</p>
                            </div>
                        </div>
                    </div>
                `
                    : ""
                }

                ${
                  item.reply !== "—"
                    ? `
                    <div class="col-12">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-reply me-2"></i>Reply Information</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Reply:</strong> ${item.reply}</p>
                                <p class="mb-0"><strong>Reply At:</strong> ${item.replyAt}</p>
                            </div>
                        </div>
                    </div>
                `
                    : ""
                }
            </div>
        `
  }

  renderPagination() {
    const totalPages = Math.ceil(this.filteredData.length / this.itemsPerPage)
    const pagination = document.getElementById("pagination")
    const paginationInfo = document.getElementById("paginationInfo")

    // Update pagination info
    const startIndex = (this.currentPage - 1) * this.itemsPerPage + 1
    const endIndex = Math.min(this.currentPage * this.itemsPerPage, this.filteredData.length)
    paginationInfo.textContent = `Showing ${startIndex} to ${endIndex} of ${this.filteredData.length} results`

    // Generate pagination
    let paginationHTML = ""

    // Previous button
    paginationHTML += `
            <li class="page-item ${this.currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="app.goToPage(${this.currentPage - 1})">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            </li>
        `

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
        paginationHTML += `
                    <li class="page-item ${i === this.currentPage ? "active" : ""}">
                        <a class="page-link" href="#" onclick="app.goToPage(${i})">${i}</a>
                    </li>
                `
      } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
        paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`
      }
    }

    // Next button
    paginationHTML += `
            <li class="page-item ${this.currentPage === totalPages ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="app.goToPage(${this.currentPage + 1})">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `

    pagination.innerHTML = paginationHTML
  }

  goToPage(page) {
    const totalPages = Math.ceil(this.filteredData.length / this.itemsPerPage)
    if (page >= 1 && page <= totalPages) {
      this.currentPage = page
      this.renderTable()
      this.renderPagination()
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  window.app = new DashboardApp()
  window.bootstrap = window.bootstrap || {} // Declare bootstrap variable if not already declared
})
