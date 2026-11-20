document.addEventListener("DOMContentLoaded", () => {
  // Get all accordion headers
  const accordionHeaders = document.querySelectorAll(".accordion-header")

  // Add click event listener to each header
  accordionHeaders.forEach((header) => {
    header.addEventListener("click", function () {
      // Toggle active class on the parent accordion item
      const accordionItem = this.parentElement
      accordionItem.classList.toggle("active")

      // Toggle the plus/minus icon
      const toggleIcon = this.querySelector(".toggle-icon")
      toggleIcon.textContent = accordionItem.classList.contains("active") ? "−" : "+"

      // Get the content panel
      const content = this.nextElementSibling

      // Toggle the content visibility
      if (accordionItem.classList.contains("active")) {
        content.style.maxHeight = content.scrollHeight + "px"
      } else {
        content.style.maxHeight = "0"
      }
    })
  })

  // Open the first accordion item by default
  if (accordionHeaders.length > 0) {
    accordionHeaders[0].click()
  }

  // Add animation to donation amounts
  const amounts = document.querySelectorAll(".amount")
  amounts.forEach((amount) => {
    const targetValue = Number.parseFloat(amount.textContent.replace(/,/g, ""))
    animateValue(amount, 0, targetValue, 1500)
  })

  // Function to animate counting up
  function animateValue(element, start, end, duration) {
    let startTimestamp = null
    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp
      const progress = Math.min((timestamp - startTimestamp) / duration, 1)
      const currentValue = Math.floor(progress * (end - start) + start)
      element.textContent = numberWithCommas(currentValue.toFixed(2))
      if (progress < 1) {
        window.requestAnimationFrame(step)
      }
    }
    window.requestAnimationFrame(step)
  }

  // Function to format numbers with commas
  function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
  }
})

