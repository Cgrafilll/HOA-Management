// Payment Management System JavaScript - Updated for All Categories

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
        
        // Get category options
        this.monthlyDuesOption = [...this.categorySelect.options].find(opt => opt.value === "Monthly Dues");
        this.penaltyFeesOption = [...this.categorySelect.options].find(opt => opt.value === "Penalty Fees");
        this.otherFeesOption = [...this.categorySelect.options].find(opt => opt.value === "Other Fees");
        
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
        this.confirmModal = new bootstrap.Modal(document.getElementById('confirmPaymentModal'));
        this.successModal = new bootstrap.Modal(document.getElementById('successPaymentModal'));
        
        const errorModalElement = document.getElementById('errorPaymentModal');
        const errorMessageElement = document.getElementById('errorMessage');
        
        if (errorModalElement && errorMessageElement) {
            this.errorModal = new bootstrap.Modal(errorModalElement);
            this.errorMessage = errorMessageElement;
        } else {
            console.warn('Error modal elements not found.');
            this.errorModal = null;
            this.errorMessage = null;
        }
    }

    attachEventListeners() {
        this.bankTransfer.addEventListener('click', () => this.selectPaymentMethod('bank'));
        this.inOffice.addEventListener('click', () => this.selectPaymentMethod('office'));
        
        this.userTypeSelect.addEventListener('change', () => this.handleUserTypeChange());
        this.userIdSelect.addEventListener('change', () => this.handleIdSelection());
        this.categorySelect.addEventListener('change', () => this.handleCategoryChange());
        this.invoiceInput.addEventListener('blur', () => this.fetchInvoiceDetails());
        this.invoiceInput.addEventListener('input', () => this.resetInvoiceValidation());
        
        this.browseLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.fileInput.click();
        });
        this.fileInput.addEventListener('change', (e) => this.handleFiles(e.target.files));
        
        this.setupDragAndDrop();
        
        document.getElementById('paymentForm').addEventListener('submit', (e) => this.handleSubmit(e));
        
        this.confirmPaymentBtn.addEventListener('click', () => this.processPayment());
    }
    
    handleCategoryChange() {
        const selectedCategory = this.categorySelect.value;
        
        // Categories that need invoice details from backend
        const fetchCategories = ['Amenity Fee', 'Monthly Dues', 'Penalty Fees', 'Other Fees'];
        
        if (!fetchCategories.includes(selectedCategory)) {
            this.clearInvoiceTable();
            this.refNo.textContent = "";
            this.residentName.textContent = "";
            this.issueDate.textContent = "";
        } else if (this.invoiceInput.value) {
            this.fetchInvoiceDetails();
        }
        
        // Validate user type restrictions
        if ((selectedCategory === 'Monthly Dues' || selectedCategory === 'Penalty Fees' || selectedCategory === 'Other Fees') 
            && this.userTypeSelect.value === 'Visitor') {
            this.refNo.textContent = selectedCategory + " only apply to homeowners/residents";
            this.clearInvoiceTable();
        }
    }

    selectPaymentMethod(method) {
        if (method === 'bank') {
            this.bankTransfer.classList.add('active');
            this.inOffice.classList.remove('active');
            this.selectedMethod.textContent = "Bank Transfer";
            this.referenceNumberGroup.style.display = 'block';
            this.referenceNumber.setAttribute('required', 'required');
        } else {
            this.inOffice.classList.add('active');
            this.bankTransfer.classList.remove('active');
            this.selectedMethod.textContent = "In-Office Payment";
            this.referenceNumberGroup.style.display = 'none';
            this.referenceNumber.removeAttribute('required');
            this.referenceNumber.value = ''; // Clear the value
        }
        this.clearFormFields();
    }

    async handleUserTypeChange() {
        const selectedType = this.userTypeSelect.value;
        
        // Show/hide categories based on user type
        if (selectedType === 'Visitor') {
            this.monthlyDuesOption.style.display = "none";
            this.penaltyFeesOption.style.display = "none";
            this.otherFeesOption.style.display = "none";
            
            if (['Monthly Dues', 'Penalty Fees', 'Other Fees'].includes(this.categorySelect.value)) {
                this.categorySelect.value = "";
            }
        } else if (selectedType === 'Homeowner/Resident') {
            this.monthlyDuesOption.style.display = "block";
            this.penaltyFeesOption.style.display = "block";
            this.otherFeesOption.style.display = "block";
        }
        
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
    
    console.log('Fetching invoice details:', {
        invoiceNumber,
        selectedCategory,
        userId,
        userType
    });
    
    this.refNo.textContent = "";
    this.residentName.textContent = "";
    this.issueDate.textContent = "";
    this.clearInvoiceTable();
    
    const lookupCategories = ["Amenity Fee", "Monthly Dues", "Penalty Fees", "Other Fees"];
    
    if (lookupCategories.includes(selectedCategory) && invoiceNumber && userId && userType) {
        
        if (['Monthly Dues', 'Penalty Fees', 'Other Fees'].includes(selectedCategory) && userType !== "Homeowner/Resident") {
            this.refNo.textContent = selectedCategory + " only apply to homeowners/residents";
            this.invoiceInput.style.borderColor = '#dc3545';
            this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
            this.clearInvoiceTable();
            return;
        }
        
        try {
            let action;
            if (selectedCategory === "Amenity Fee") {
                action = 'get_amenity_booking_by_invoice';
            } else {
                action = 'get_billing_by_invoice';
            }
                
            const params = new URLSearchParams({
                action: action,
                invoice_number: invoiceNumber,
                user_id: userId,
                user_type: userType,
                category: selectedCategory
            });
            
            const url = `?${params.toString()}`;
            console.log('Fetch URL:', url);
            
            const response = await fetch(url);
            const text = await response.text();
            console.log('Raw response:', text);
            
            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(text);
                console.log('Parsed result:', result);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response was not valid JSON');
                throw new Error('Invalid response from server');
            }
            
            if (result.success) {
                const data = result.data;
                this.currentInvoiceData = data;
                
                this.refNo.textContent = data.reference_number;
                this.residentName.textContent = `${data.first_name} ${data.middle_name} ${data.last_name}`;
                this.issueDate.textContent = new Date(data.created_at).toLocaleDateString();
                
                if (['Monthly Dues', 'Penalty Fees', 'Other Fees'].includes(selectedCategory)) {
                    if (data.billing_month) {
                        const billingMonth = new Date(data.billing_month).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long' 
                        });
                        this.issueDate.textContent += ` (${billingMonth})`;
                    }
                    if (data.description) {
                        this.refNo.textContent += ` - ${data.description}`;
                    }
                }
                
                this.populateInvoiceTable(data.items);
                
                this.subtotal.textContent = `₱${data.subtotal}`;
                this.previouslyPaid.textContent = `₱${data.amount_paid}`;
                this.balanceDue.textContent = `₱${data.balance_due}`;
                
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
                console.error('Server returned error:', result.error);
                this.refNo.textContent = result.error || "Invoice not found";
                this.invoiceInput.style.borderColor = '#dc3545';
                this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                this.clearInvoiceTable();
            }
        } catch (error) {
            console.error('Error fetching invoice details:', error);
            this.refNo.textContent = "Error loading: " + error.message;
            this.invoiceInput.style.borderColor = '#dc3545';
            this.invoiceInput.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
            this.clearInvoiceTable();
        }
    } else {
        console.log('Missing required fields for fetch');
        this.clearInvoiceTable();
    }
}
    
    populateInvoiceTable(items) {
        if (!items || items.length === 0) {
            this.clearInvoiceTable();
            return;
        }
        
        this.invoiceTableBody.innerHTML = '';
        
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
        const maxSize = 10 * 1024 * 1024;
        
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid file type for proof of payment (JPEG, PNG, GIF, PDF)');
            return;
        }
        
        if (file.size > maxSize) {
            alert('File size must be less than 10MB');
            return;
        }
        
        const dt = new DataTransfer();
        dt.items.add(file);
        this.fileInput.files = dt.files;
        
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
        this.userTypeSelect.value = "";
        this.userIdSelect.innerHTML = '<option value="">First select user type</option>';
        this.userIdSelect.disabled = true;
        this.categorySelect.value = "";
        this.invoiceInput.value = "";
        this.amountPaid.value = "";
        this.referenceNumber.value = "";
        
        this.refNo.textContent = "";
        this.residentName.textContent = "";
        this.issueDate.textContent = "";
        
        this.clearInvoiceTable();
        
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
        
        this.fileDropArea.style.borderColor = '#d1d5db';
        this.fileDropArea.style.backgroundColor = '#f9fafb';
        
        this.fileInput.value = '';
        this.filePreview.innerHTML = '';
    }

    handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Base required fields
        const requiredFields = [
            { field: this.userTypeSelect, name: 'User Type' },
            { field: this.userIdSelect, name: 'User ID' },
            { field: this.categorySelect, name: 'Category' },
            { field: this.invoiceInput, name: 'Invoice Number' },
            { field: this.amountPaid, name: 'Amount Paid' }
        ];
        
        // Add reference number validation for bank transfer only
        if (this.selectedMethod.textContent === "Bank Transfer") {
            requiredFields.push({ field: this.referenceNumber, name: 'Reference Number' });
        }
        
        let isValid = true;
        let firstInvalidField = null;
        
        requiredFields.forEach(item => {
            const field = item.field;
            if (!field.value || field.value.trim() === '') {
                field.classList.add('is-invalid');
                field.style.borderColor = '#dc3545';
                field.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            } else {
                field.classList.remove('is-invalid');
                field.style.borderColor = '#dee2e6';
                field.style.boxShadow = 'none';
            }
        });
        
        // Validate amount is positive
        if (!this.amountPaid.value || parseFloat(this.amountPaid.value) <= 0) {
            this.amountPaid.classList.add('is-invalid');
            this.amountPaid.style.borderColor = '#dc3545';
            this.amountPaid.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.15)';
            isValid = false;
            if (!firstInvalidField) {
                firstInvalidField = this.amountPaid;
            }
        }
        
        // Validate proof of payment for BOTH payment methods
        if (!this.fileInput.files.length) {
            this.fileDropArea.style.borderColor = '#dc3545';
            this.fileDropArea.style.backgroundColor = '#f8d7da';
            isValid = false;
            if (!firstInvalidField) {
                // Scroll to file upload area
                this.fileDropArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Different message based on payment method
            const paymentType = this.selectedMethod.textContent === "Bank Transfer" 
                ? "bank transfer" 
                : "in-office payment";
            this.showErrorModal(`Please upload proof of payment for ${paymentType}.`);
        } else {
            this.fileDropArea.style.borderColor = '#d1d5db';
            this.fileDropArea.style.backgroundColor = '#f9fafb';
        }
        
        if (!isValid) {
            // Focus on first invalid field
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            return;
        }
        
        // Additional validation for invoice-based payments
        const invoiceCategories = ['Amenity Fee', 'Monthly Dues', 'Penalty Fees', 'Other Fees'];
        if (invoiceCategories.includes(this.categorySelect.value)) {
            if (!this.currentInvoiceData) {
                this.showErrorModal(`Please enter a valid invoice number for ${this.categorySelect.value} payments.`);
                return;
            }
            
            const amountPaid = parseFloat(this.amountPaid.value);
            const balanceDue = parseFloat(this.currentInvoiceData.balance_due.replace(/,/g, ''));
            
            if (amountPaid > balanceDue) {
                this.showErrorModal(`The amount entered (₱${amountPaid.toFixed(2)}) exceeds the balance due (₱${balanceDue.toFixed(2)}). Please enter a valid amount.`);
                return;
            }
        }
        
        this.showConfirmationModal();
    }
    showErrorModal(message) {
        if (this.errorModal && this.errorMessage) {
            this.errorMessage.textContent = message;
            this.errorModal.show();
        } else {
            alert(message);
        }
    }

    showConfirmationModal() {
        const selectedUserOption = this.userIdSelect.options[this.userIdSelect.selectedIndex];
        const userName = selectedUserOption.textContent.split(' - ')[1] || 'Unknown';
        
        this.confirmName.textContent = userName;
        this.confirmCategory.textContent = this.categorySelect.value;
        this.confirmInvoice.textContent = this.invoiceInput.value;
        this.confirmAmount.textContent = `₱${parseFloat(this.amountPaid.value).toFixed(2)}`;
        this.confirmMethod.textContent = this.selectedMethod.textContent;
        
        this.confirmModal.show();
    }
    async processPayment() {
        try {
            this.confirmPaymentBtn.disabled = true;
            this.confirmPaymentBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Processing...';
            
            const formData = new FormData();
            formData.append('category', this.categorySelect.value);
            formData.append('user_type', this.userTypeSelect.value);
            formData.append('user_id', this.userIdSelect.value);
            formData.append('invoice_number', this.invoiceInput.value);
            formData.append('amount', this.amountPaid.value);
            formData.append('payment_method', this.selectedMethod.textContent);
            formData.append('reference_number', this.referenceNumber.value || '');
            
            if (this.fileInput.files.length > 0) {
                formData.append('proof_of_payment', this.fileInput.files[0]);
            }
            
            // FIX: Send to the same page with action parameter
            const response = await fetch('payment.php?action=process_payment', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.confirmModal.hide();
                this.successModal.show();
                
                setTimeout(() => {
                    this.clearFormFields();
                    this.selectPaymentMethod('bank');
                }, 1000);
                
            } else {
                throw new Error(result.error || 'Payment processing failed');
            }
            
        } catch (error) {
            console.error('Payment processing error:', error);
            this.showErrorModal('Error processing payment: ' + error.message);
        } finally {
            this.confirmPaymentBtn.disabled = false;
            this.confirmPaymentBtn.innerHTML = 'Process Payment';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new PaymentManager();
});