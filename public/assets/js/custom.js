//-------------------Footer Year Start---------------------

const currentYear = new Date().getFullYear(); // Get the current year
document.getElementById('year').textContent = currentYear; // Insert the year into the span with id="year"

//-------------------Footer Year End---------------------




// Get the current date
const today = new Date();

// Define the review month (March)
const reviewMonth = 2; // March (0-based index)

// Determine last and next review years
const lastReviewYear = today.getMonth() < reviewMonth ? currentYear - 1 : currentYear;
const nextReviewYear = lastReviewYear + 1;

// Insert dates into the table
document.getElementById("lastReview").textContent = `March ${lastReviewYear}`;
document.getElementById("nextReview").textContent = `March ${nextReviewYear}`;





// Define the global discount value in one place
const discountValue = 40; // Change this value when needed

// Update all instances of discount badges
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".discountValue").forEach(span => {
        span.textContent = `\u00A0${discountValue}%`; // \u00A0 is a non-breaking space
    });
});





