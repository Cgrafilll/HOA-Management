// Payment Management System JavaScript

class PaymentManager {
    constructor() {
        this.initializeElements();
        this.attachEventListeners();
        this.initializeModals();
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
        this.referenceNumber = document.getElementById('referenceNumber');
        this.referenceNumberGroup = document.getElementById('referenceNumberGroup');
        
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
        
        // Modal confirmation elements
        this.confirmName = document.getElementById('confirmName');
        this.confirmCategory = document.getElementById('confirmCategory');
        this.confirmInvoice = document.getElementById('confirmInvoice');
        this.confirmAmount = document.getElementById('confirmAmount');
        this.confirmMethod = document.getElementById('confirmMethod');
        this.confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
    }

    initializeModals() {
        // Initialize Bootstrap modals
        this.confirmModal = new bootstrap.Modal(document.getElementById('confirmPaymentModal'));
        this.successModal = new bootstrap.Modal(document.getElementById('successPaymentModal'));
        
        // Check if error modal exists before initializing
        const errorModalElement = document.getElementById('errorPaymentModal');
        const errorMessageElement = document.getElementById('errorMessage');
        
        if (errorModalElement && errorMessageElement) {
            this.errorModal = new bootstrap.Modal(errorModalElement);
            this.errorMessage = errorMessageElement;
        } else {
            console.warn('Error modal elements not found. Error modal functionality will be disabled.');
            this.errorModal = null;
            this.errorMessage = null;
        }
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
        
        // Modal confirmation button
        this.confirmPaymentBtn.addEventListener('click', () => this.processPayment());
    }
    
    handleCategoryChange() {
        const selectedCategory = this.categorySelect.value;
        
        // Clear table if not Amenity Fee or Monthly Dues
        if (selectedCategory !== 'Amenity Fee' && selectedCategory !== 'Monthly Dues') {
            this.clearInvoiceTable();
            this.refNo.textContent = "";
            this.residentName.textContent = "";
            this.issueDate.textContent = "";
        } else if (this.invoiceInput.value) {
            // If switching to Amenity Fee or Monthly Dues and invoice exists, fetch details
            this.fetchInvoiceDetails();
        }
        
        // Show validation message if Monthly Dues is selected but user type is Visitor
        if (selectedCategory === 'Monthly Dues' && this.userTypeSelect.value === 'Visitor') {
            this.refNo.textContent = "Monthly dues only apply to homeowners/residents";
            this.clearInvoiceTable();
        }
    }

    selectPaymentMethod(method) {
        if (method === 'bank') {
            this.bankTransfer.classList.add('active');
            this.inOffice.classList.remove('active');
            this.selectedMethod.textContent = "Bank Transfer";
            this.referenceNumberGroup.style.display = 'block';
        } else {
            this.inOffice.classList.add('active');
            this.bankTransfer.classList.remove('active');
            this.selectedMethod.textContent = "In-Office Payment";
            this.referenceNumberGroup.style.display = 'none';
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
        
        // Only fetch for Amenity Fee or Monthly Dues categories
        if ((selectedCategory === "Amenity Fee" || selectedCategory === "Monthly Dues") && invoiceNumber && userId && userType) {
            
            // Validate Monthly Dues is only for homeowners/residents
            if (selectedCategory === "Monthly Dues" && userType !== "Homeowner/Resident") {
                this.refNo.textContent = "Monthly dues only apply to homeowners/residents";
                this.invoiceInput.style.borderColor = '#dc3545';
                this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                this.clearInvoiceTable();
                return;
            }
            
            try {
                // Determine which endpoint to use
                const action = selectedCategory === "Amenity Fee" 
                    ? 'get_amenity_booking_by_invoice' 
                    : 'get_monthly_dues_by_invoice';
                    
                const params = new URLSearchParams({
                    action: action,
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
                    
                    // For Monthly Dues, also show billing month if available
                    if (selectedCategory === "Monthly Dues" && data.billing_month) {
                        const billingMonth = new Date(data.billing_month).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long' 
                        });
                        this.issueDate.textContent += ` (${billingMonth})`;
                    }
                    
                    // Populate table
                    this.populateInvoiceTable(data.items);
                    
                    // Populate summary
                    this.subtotal.textContent = `₱${data.subtotal}`;
                    this.previouslyPaid.textContent = `₱${data.amount_paid}`;
                    this.balanceDue.textContent = `₱${data.balance_due}`;
                    
                    // Add status indicator
                    if (data.status === 'Partial') {
                        this.balanceDue.parentElement.classList.add('text-warning');
                        this.balanceDue.parentElement.classList.remove('text-success');
                    } else if (data.status === 'Paid' || data.status === 'Completed') {
                        this.balanceDue.parentElement.classList.add('text-success');
                        this.balanceDue.parentElement.classList.remove('text-warning');
                    } else {
                        this.balanceDue.parentElement.classList.remove('text-success', 'text-warning');
                    }
                    
                    this.invoiceInput.style.borderColor = '#198754';
                    this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(25, 135, 84, 0.15)';
                } else {
                    this.refNo.textContent = result.error || "Invoice not found";
                    this.invoiceInput.style.borderColor = '#dc3545';
                    this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                    this.clearInvoiceTable();
                }
            } catch (error) {
                console.error('Error fetching invoice details:', error);
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
        this.referenceNumber.value = "";
        
        // Reset display fields
        this.refNo.textContent = "";
        this.residentName.textContent = "";
        this.issueDate.textContent = "";
        
        // Reset table and summary
        this.clearInvoiceTable();
        
        // Reset all validation styles
        const fieldsToReset = [
            this.userTypeSelect,
            this.userIdSelect,
            this.categorySelect,
            this.invoiceInput,
            this.amountPaid
        ];
        
        fieldsToReset.forEach(field => {
            field.classList.remove('is-invalid');
            field.style.borderColor = '#dee2e6';
            field.style.boxShadow = 'none';
        });
        
        // Reset file drop area styling
        this.fileDropArea.style.borderColor = '#d1d5db';
        this.fileDropArea.style.backgroundColor = '#f9fafb';
        
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
                field.style.borderColor = '#dc3545';
                field.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                field.style.borderColor = '#dee2e6';
                field.style.boxShadow = 'none';
            }
        });
        
        // Validate amount paid field specifically
        if (!this.amountPaid.value || parseFloat(this.amountPaid.value) <= 0) {
            this.amountPaid.classList.add('is-invalid');
            this.amountPaid.style.borderColor = '#dc3545';
            this.amountPaid.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
            isValid = false;
        }
        
        // Check if proof of payment is uploaded for bank transfer and highlight if missing
        if (this.selectedMethod.textContent === "Bank Transfer" && !this.fileInput.files.length) {
            this.fileDropArea.style.borderColor = '#dc3545';
            this.fileDropArea.style.backgroundColor = '#f8d7da';
            isValid = false;
        } else {
            // Reset file drop area styling if valid
            this.fileDropArea.style.borderColor = '#d1d5db';
            this.fileDropArea.style.backgroundColor = '#f9fafb';
        }
        
        if (!isValid) {
            return;
        }
        
                
        if (!isValid) {
            return;
        }
        
        // Additional validation for Amenity Fee and Monthly Dues payments
        if (this.categorySelect.value === 'Amenity Fee' || this.categorySelect.value === 'Monthly Dues') {
            if (!this.currentInvoiceData) {
                this.showErrorModal(`Please enter a valid invoice number for ${this.categorySelect.value} payments.`);
                return;
            }
            
            // Check if payment amount exceeds balance due - USE ERROR MODAL FOR THIS
            const amountPaid = parseFloat(this.amountPaid.value);
            const balanceDue = parseFloat(this.currentInvoiceData.balance_due.replace(/,/g, ''));
            
            if (amountPaid > balanceDue) {
                this.showErrorModal(`The amount entered (₱${amountPaid.toFixed(2)}) exceeds the balance due (₱${balanceDue.toFixed(2)}). Please enter a valid amount.`);
                return;
            }
        }
        
        // Show confirmation modal if all validations pass
        this.showConfirmationModal();
    }

    showErrorModal(message) {
        if (this.errorModal && this.errorMessage) {
            this.errorMessage.textContent = message;
            this.errorModal.show();
        } else {
            // Fallback to alert if error modal is not available
            alert(message);
        }
    }

    showConfirmationModal() {
        // Populate confirmation modal with payment details
        const selectedUserOption = this.userIdSelect.options[this.userIdSelect.selectedIndex];
        const userName = selectedUserOption.textContent.split(' - ')[1] || 'Unknown';
        
        this.confirmName.textContent = userName;
        this.confirmCategory.textContent = this.categorySelect.value;
        this.confirmInvoice.textContent = this.invoiceInput.value;
        this.confirmAmount.textContent = `₱${parseFloat(this.amountPaid.value).toFixed(2)}`;
        this.confirmMethod.textContent = this.selectedMethod.textContent;
        
        // Show the modal
        this.confirmModal.show();
    }

    async processPayment() {
        try {
            // Disable the confirm button to prevent double submission
            this.confirmPaymentBtn.disabled = true;
            this.confirmPaymentBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Processing...';
            
            // Create FormData for file upload
            const formData = new FormData();
            formData.append('action', 'process_payment');
            formData.append('category', this.categorySelect.value);
            formData.append('user_type', this.userTypeSelect.value);
            formData.append('user_id', this.userIdSelect.value);
            formData.append('invoice_number', this.invoiceInput.value);
            formData.append('amount', this.amountPaid.value);
            formData.append('payment_method', this.selectedMethod.textContent);
            formData.append('reference_number', this.referenceNumber.value || '');
            
            // Add file if exists
            if (this.fileInput.files.length > 0) {
                formData.append('proof_of_payment', this.fileInput.files[0]);
            }
            
            const response = await fetch('payment/process_payment.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Hide confirmation modal
                this.confirmModal.hide();
                
                // Show success modal
                this.successModal.show();
                
                // Clear form after a short delay
                setTimeout(() => {
                    this.clearFormFields();
                    this.selectPaymentMethod('bank'); // Reset to default
                }, 1000);
                
            } else {
                throw new Error(result.error || 'Payment processing failed');
            }
            
        } catch (error) {
            console.error('Payment processing error:', error);
            this.showErrorModal('Error processing payment: ' + error.message);
        } finally {
            // Re-enable the confirm button
            this.confirmPaymentBtn.disabled = false;
            this.confirmPaymentBtn.innerHTML = 'Process Payment';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new PaymentManager();
});