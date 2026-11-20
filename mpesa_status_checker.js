document.addEventListener("DOMContentLoaded", () => {
  // Check if there's an ongoing M-PESA transaction
  if (sessionStorage.getItem("mpesa_transaction_pending")) {
    const statusCheckInterval = 5000 // 5 seconds
    const maxChecks = 12 // Check for 1 minute (12 * 5 seconds)
    let checkCount = 0

    // Show transaction pending message
    const pendingMessage = document.createElement("div")
    pendingMessage.className = "mpesa-pending-message"
    pendingMessage.innerHTML = `
            <div class="spinner"></div>
            <p>Waiting for M-PESA payment confirmation...</p>
        `

    const mpesaForm = document.querySelector(".mpesa-form")
    if (mpesaForm) {
      mpesaForm.appendChild(pendingMessage)
    }

    // Function to check transaction status
    function checkTransactionStatus() {
      fetch("mpesa_callback_status.php")
        .then((response) => response.json())
        .then((data) => {
          checkCount++

          if (data.ResultCode === 0) {
            // Transaction successful
            sessionStorage.removeItem("mpesa_transaction_pending")
            window.location.href = "donation_tracker.php?success=true"
          } else if (checkCount >= maxChecks) {
            // Stop checking after max attempts
            pendingMessage.innerHTML = `
                            <p>We haven't received confirmation yet. Please check your M-PESA messages.</p>
                            <a href="donation_tracker.php" class="check-status-btn">Check Donation Status</a>
                        `
            sessionStorage.removeItem("mpesa_transaction_pending")
          }
        })
        .catch((error) => {
          console.error("Error checking transaction status:", error)
        })
    }

    // Start periodic checking
    const statusChecker = setInterval(() => {
      checkTransactionStatus()

      if (checkCount >= maxChecks) {
        clearInterval(statusChecker)
      }
    }, statusCheckInterval)

    // Initial check
    checkTransactionStatus()
  }

  // Set transaction pending flag when form is submitted
  const mpesaForm = document.querySelector(".mpesa-form")
  if (mpesaForm) {
    mpesaForm.addEventListener("submit", () => {
      sessionStorage.setItem("mpesa_transaction_pending", "true")
    })
  }
})

