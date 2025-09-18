// Payment Management System JavaScript

class PaymentManager {
    constructor() {
        this.initializeElements();
        this.attachEventListeners();
    }

    initializeElements() {
        // Payment method cards
        this.bankTransfer = document.getElementById('bankTransfer');
        this.inOffice = document.getElementById('inOffice');
        this.selectedMethod = document.getElementById('selectedMethod');
        
        // Form elements
        this.userTypeSelect = document.getElementById('userTypeSelect');
        this.userIdSelect = document.getElementById('userIdSelect');
        this.idLabel = document.getElementById('idLabel');
        this.loadingIndicator = document.getElementById('loadingIndicator');
        this.categorySelect = document.getElementById('categorySelect');
        this.invoiceInput = document.getElementById('invoiceInput');
        this.amountPaid = document.getElementById('amountPaid');
        
        // Display elements
        this.refNo = document.getElementById('refNo');
        this.residentName = document.getElementById('residentName');
        this.issueDate = document.getElementById('issueDate');
        
        // Table and summary elements
        this.invoiceTableBody = document.getElementById('invoiceTableBody');
        this.subtotal = document.getElementById('subtotal');
        this.previouslyPaid = document.getElementById('previouslyPaid');
        this.balanceDue = document.getElementById('balanceDue');
        
        // File upload elements
        this.fileDropArea = document.getElementById('fileDropArea');
        this.fileInput = document.getElementById('fileInput');
        this.browseLink = document.getElementById('browseLink');
        this.filePreview = document.getElementById('filePreview');
        
        // Get Monthly Dues option reference
        this.monthlyOption = [...this.categorySelect.options].find(opt => opt.value === "Monthly Dues");
        
        // Store current invoice data
        this.currentInvoiceData = null;
    }

    attachEventListeners() {
        // Payment method selection
        this.bankTransfer.addEventListener('click', () => this.selectPaymentMethod('bank'));
        this.inOffice.addEventListener('click', () => this.selectPaymentMethod('office'));
        
        // Form field changes
        this.userTypeSelect.addEventListener('change', () => this.handleUserTypeChange());
        this.userIdSelect.addEventListener('change', () => this.handleIdSelection());
        this.categorySelect.addEventListener('change', () => this.handleCategoryChange());
        this.invoiceInput.addEventListener('blur', () => this.fetchInvoiceDetails());
        this.invoiceInput.addEventListener('input', () => this.resetInvoiceValidation());
        
        // File upload
        this.browseLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.fileInput.click();
        });
        this.fileInput.addEventListener('change', (e) => this.handleFiles(e.target.files));
        
        // Drag and drop
        this.setupDragAndDrop();
        
        // Form submission
        document.getElementById('paymentForm').addEventListener('submit', (e) => this.handleSubmit(e));
    }
    
    handleCategoryChange() {
        const selectedCategory = this.categorySelect.value;
        
        // Clear table if not Amenity Fee
        if (selectedCategory !== 'Amenity Fee') {
            this.clearInvoiceTable();
            this.refNo.textContent = "";
            this.residentName.textContent = "";
            this.issueDate.textContent = "";
        } else if (this.invoiceInput.value) {
            // If switching to Amenity Fee and invoice exists, fetch details
            this.fetchInvoiceDetails();
        }
    }

    selectPaymentMethod(method) {
        if (method === 'bank') {
            this.bankTransfer.classList.add('active');
            this.inOffice.classList.remove('active');
            this.selectedMethod.textContent = "Bank Transfer";
        } else {
            this.inOffice.classList.add('active');
            this.bankTransfer.classList.remove('active');
            this.selectedMethod.textContent = "In-Office Payment";
        }
        this.clearFormFields();
    }

    async handleUserTypeChange() {
        const selectedType = this.userTypeSelect.value;
        
        // Handle Monthly Dues visibility
        if (selectedType === 'Visitor') {
            this.monthlyOption.style.display = "none";
            if (this.categorySelect.value === "Monthly Dues") {
                this.categorySelect.value = "";
            }
        } else if (selectedType === 'Homeowner/Resident') {
            this.monthlyOption.style.display = "block";
        }
        
        // Reset and load IDs
        this.userIdSelect.innerHTML = '<option value="">Loading...</option>';
        this.userIdSelect.disabled = true;
        this.loadingIndicator.classList.remove('d-none');
        
        if (selectedType) {
            await this.loadUserIds(selectedType);
        }
        
        this.loadingIndicator.classList.add('d-none');
        this.userIdSelect.disabled = false;
        this.userIdSelect.classList.add('fade-in');
        setTimeout(() => this.userIdSelect.classList.remove('fade-in'), 300);
    }

    async loadUserIds(userType) {
        try {
            const endpoint = userType === 'Homeowner/Resident' ? 'get_households' : 'get_visitors';
            const labelText = userType === 'Homeowner/Resident' ? 'Resident ID' : 'Visitor ID';
            
            this.idLabel.innerHTML = `${labelText}<small class="fw-bold text-danger">*</small>`;
            
            const response = await fetch(`?action=${endpoint}`);
            const result = await response.json();
            
            if (result.success) {
                this.userIdSelect.innerHTML = `<option value="">Select ${labelText}</option>`;
                result.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item[userType === 'Homeowner/Resident' ? 'household_id' : 'visitor_id'];
                    option.textContent = `${option.value} - ${item.name}`;
                    this.userIdSelect.appendChild(option);
                });
            } else {
                this.userIdSelect.innerHTML = '<option value="">Error loading data</option>';
                console.error('Error:', result.error);
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            this.userIdSelect.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    handleIdSelection() {
        if (this.userIdSelect.value) {
            this.userIdSelect.style.borderColor = '#198754';
            this.userIdSelect.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
        } else {
            this.userIdSelect.style.borderColor = '#dee2e6';
            this.userIdSelect.style.boxShadow = 'none';
        }
    }

    async fetchInvoiceDetails() {
        const invoiceNumber = this.invoiceInput.value.trim();
        const selectedCategory = this.categorySelect.value;
        const userId = this.userIdSelect.value;
        const userType = this.userTypeSelect.value;
        
        // Reset display fields
        this.refNo.textContent = "";
        this.residentName.textContent = "";
        this.issueDate.textContent = "";
        this.clearInvoiceTable();
        
        // Only fetch for Amenity Fee category
        if (selectedCategory === "Amenity Fee" && invoiceNumber && userId && userType) {
            try {
                const params = new URLSearchParams({
                    action: 'get_amenity_booking_by_invoice',
                    invoice_number: invoiceNumber,
                    user_id: userId,
                    user_type: userType
                });
                
                const response = await fetch(`?${params}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    this.currentInvoiceData = data;
                    
                    // Populate basic info
                    this.refNo.textContent = data.reference_number;
                    this.residentName.textContent = `${data.first_name} ${data.middle_name} ${data.last_name}`;
                    this.issueDate.textContent = new Date(data.created_at).toLocaleDateString();
                    
                    // Populate table
                    this.populateInvoiceTable(data.items);
                    
                    // Populate summary
                    this.subtotal.textContent = `₱${data.subtotal}`;
                    this.previouslyPaid.textContent = `₱${data.amount_paid}`;
                    this.balanceDue.textContent = `₱${data.balance_due}`;
                    
                    // Add status indicator if partially paid
                    if (data.status === 'Partial') {
                        this.balanceDue.parentElement.classList.add('text-warning');
                        this.balanceDue.parentElement.classList.remove('text-success');
                    } else if (data.status === 'Paid') {
                        this.balanceDue.parentElement.classList.add('text-success');
                        this.balanceDue.parentElement.classList.remove('text-warning');
                    }
                    
                    this.invoiceInput.style.borderColor = '#198754';
                    this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
                } else {
                    this.refNo.textContent = "Invoice not found";
                    this.invoiceInput.style.borderColor = '#dc3545';
                    this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                    this.clearInvoiceTable();
                }
            } catch (error) {
                console.error('Error fetching amenity booking:', error);
                this.refNo.textContent = "Error loading";
                this.invoiceInput.style.borderColor = '#dc3545';
                this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                this.clearInvoiceTable();
            }
        } else {
            this.clearInvoiceTable();
        }
    }
    
    populateInvoiceTable(items) {
        if (!items || items.length === 0) {
            this.clearInvoiceTable();
            return;
        }
        
        // Clear existing rows
        this.invoiceTableBody.innerHTML = '';
        
        // Add items to table
        items.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.category}</td>
                <td>${item.item}</td>
                <td>₱${item.rate}</td>
                <td>${item.qty}</td>
                <td>₱${item.amount}</td>
            `;
            this.invoiceTableBody.appendChild(row);
        });
        
        // Add empty rows if needed to maintain minimum height
        const currentRows = items.length;
        const minRows = 3;
        if (currentRows < minRows) {
            for (let i = currentRows; i < minRows; i++) {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="5">&nbsp;</td>';
                this.invoiceTableBody.appendChild(emptyRow);
            }
        }
    }
    
    clearInvoiceTable() {
        this.invoiceTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No invoice data loaded</td></tr>';
        this.subtotal.textContent = '₱0.00';
        this.previouslyPaid.textContent = '₱0.00';
        this.balanceDue.textContent = '₱0.00';
        this.currentInvoiceData = null;
    }

    resetInvoiceValidation() {
        this.invoiceInput.style.borderColor = '#dee2e6';
        this.invoiceInput.style.boxShadow = 'none';
    }

    setupDragAndDrop() {
        this.fileDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.fileDropArea.classList.add('dragover');
        });
        
        this.fileDropArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.fileDropArea.classList.remove('dragover');
        });
        
        this.fileDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.fileDropArea.classList.remove('dragover');
            this.handleFiles(e.dataTransfer.files);
        });
    }

    handleFiles(files) {
        if (files.length === 0) return;
        
        const file = files[0];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        // Validate file type
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid file type for proof of payment (JPEG, PNG, GIF, PDF)');
            return;
        }
        
        // Validate file size
        if (file.size > maxSize) {
            alert('File size must be less than 10MB');
            return;
        }
        
        // Update file input
        const dt = new DataTransfer();
        dt.items.add(file);
        this.fileInput.files = dt.files;
        
        // Display preview
        this.displayFilePreview(file);
    }

    displayFilePreview(file) {
        this.filePreview.innerHTML = '';
        
        const previewContainer = document.createElement('div');
        previewContainer.className = 'alert alert-success d-flex align-items-center justify-content-between';
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'd-flex align-items-center';
        
        const fileIcon = document.createElement('i');
        fileIcon.className = file.type.startsWith('image/') 
            ? 'bi bi-file-earmark-image me-2' 
            : 'bi bi-file-earmark-pdf me-2';
        
        const fileName = document.createElement('span');
        fileName.textContent = `${file.name} (${this.formatFileSize(file.size)})`;
        
        fileInfo.appendChild(fileIcon);
        fileInfo.appendChild(fileName);
        
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn-close';
        removeButton.onclick = () => {
            this.fileInput.value = '';
            this.filePreview.innerHTML = '';
        };
        
        previewContainer.appendChild(fileInfo);
        previewContainer.appendChild(removeButton);
        this.filePreview.appendChild(previewContainer);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    clearFormFields() {
        // Reset form fields
        this.userTypeSelect.value = "";
        this.userIdSelect.innerHTML = '<option value="">First select user type</option>';
        this.userIdSelect.disabled = true;
        this.categorySelect.value = "";
        this.invoiceInput.value = "";
        this.amountPaid.value = "";
        
        // Reset display fields
        this.refNo.textContent = "";
        this.residentName.textContent = "";
        this.issueDate.textContent = "";
        
        // Reset table and summary
        this.clearInvoiceTable();
        
        // Reset styles
        this.invoiceInput.style.borderColor = '#dee2e6';
        this.invoiceInput.style.boxShadow = 'none';
        this.userIdSelect.style.borderColor = '#dee2e6';
        this.userIdSelect.style.boxShadow = 'none';
        
        // Reset file upload
        this.fileInput.value = '';
        this.filePreview.innerHTML = '';
    }

    handleSubmit(e) {
        e.preventDefault();
        
        // Validate required fields
        const requiredFields = [
            this.userTypeSelect,
            this.userIdSelect,
            this.categorySelect,
            this.invoiceInput,
            this.amountPaid
        ];
        
        let isValid = true;
        requiredFields.forEach(field => {
            if (!field.value) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields');
            return;
        }
        
        // Check if proof of payment is uploaded for bank transfer
        if (this.selectedMethod.textContent === "Bank Transfer" && !this.fileInput.files.length) {
            alert('Please upload proof of payment for bank transfers');
            return;
        }
        
        // Additional validation for Amenity Fee payments
        if (this.categorySelect.value === 'Amenity Fee') {
            if (!this.currentInvoiceData) {
                alert('Please enter a valid invoice number for Amenity Fee payments');
                return;
            }
            
            // Check if payment amount is valid
            const amountPaid = parseFloat(this.amountPaid.value);
            const balanceDue = parseFloat(this.currentInvoiceData.balance_due.replace(/,/g, ''));
            
            if (amountPaid > balanceDue) {
                const confirmOverpayment = confirm(
                    `The amount entered (₱${amountPaid.toFixed(2)}) exceeds the balance due (₱${balanceDue.toFixed(2)}). Do you want to proceed?`
                );
                if (!confirmOverpayment) return;
            }
        }
        
        // Prepare form data
        const formData = {
            userType: this.userTypeSelect.value,
            userId: this.userIdSelect.value,
            category: this.categorySelect.value,
            invoice: this.invoiceInput.value,
            amount: this.amountPaid.value,
            method: this.selectedMethod.textContent,
            file: this.fileInput.files[0],
            invoiceData: this.currentInvoiceData
        };
        
        console.log('Form submitted with:', formData);
        
        // Here you would normally send the data to the server
        // For now, just show success message
        alert('Payment processed successfully!');
        this.clearFormFields();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new PaymentManager();
});