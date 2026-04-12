//Number only Input
function numberonly(input) {
  var num = /[^0-9]/gi;
  input.value = input.value.replace(num, "");
}

/* Form Validations */
function validateTenDigits(inputString) {
  const regex = /^\d{10}$/; // Matches exactly 10 digits
  return regex.test(inputString);
}

function isNumeric(inputString) {
  return /^\d+$/.test(inputString);
}

//FAQ Script
document.addEventListener("DOMContentLoaded", () => {
  const accordionItems = document.querySelectorAll(".accordion-item");

  accordionItems.forEach((item) => {
    const header = item.querySelector(".accordion-header");
    const content = item.querySelector(".accordion-content");

    header.addEventListener("click", () => {
      const isActive = item.classList.contains("active");

      // Close all items first
      accordionItems.forEach((otherItem) => {
        otherItem.classList.remove("active");
        otherItem
          .querySelector(".accordion-header")
          .setAttribute("aria-expanded", "false");
      });

      // Open clicked item if it was closed
      if (!isActive) {
        item.classList.add("active");
        header.setAttribute("aria-expanded", "true");
      }
    });
  });

  // Keyboard accessibility
  document.querySelectorAll(".accordion-header").forEach((header) => {
    header.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        header.click();
      }
    });
  });
});

// Poisition Tpye
document.getElementById("position").addEventListener("change", function () {
  const stateDropdown = document.getElementById("state");
  const districtDropdown = document.getElementById("district");
  const exclude = this.value;
  if (exclude && exclude.trim() !== "") {
    stateDropdown.disabled = false;
    stateDropdown.innerHTML = '<option value="">-- Select a State --</option>';
    loadState();
  } else {
    stateDropdown.disabled = true;
    stateDropdown.value = "";
    districtDropdown.disabled = true;
    districtDropdown.value = "";
  }
});

// Initialize Cashfree (use "sandbox" for testing, "production" for live)
const cashfree = Cashfree({ mode: "sandbox" });

/* Form Submittion */
document
  .getElementById("submitBtn")
  .addEventListener("click", function (event) {
    event.preventDefault();
    const name = document.getElementById("name").value.trim();
    const email = document.getElementById("email").value.trim();
    const mobile = document.getElementById("mobile").value.trim();
    const experience = document.querySelector(
      'input[name="experience"]:checked',
    )?.value;
    const position = document.getElementById("position").value.trim();
    const district = document.getElementById("district").value.trim();
    const block = document.getElementById("block").value.trim();
    const terms_check = document.getElementById("terms_check");
    //const location = block || district;
    const location = position === "BCO" ? block : district;

    document.addEventListener("change", (e) => {
      if (e.target && e.target.id === "terms_check") {
        console.log(e.target.checked);
      }
    });
    // Input field validation
    if (
      !name ||
      !email ||
      !mobile ||
      !experience ||
      !position ||
      !location ||
      !terms_check.checked
    ) {
      alert("Please fill all fields");
    } else {
      const formData = {
        name: name,
        email: email,
        mobile: mobile,
        experience: experience,
        position: position,
        location: location,
      };

      cashfreePayment(formData);
    }
  });

// Function for payment order
function cashfreePayment(data) {
  //1. Create order in php (Send ₹200 & student details)
  fetch("assets/cashfree/create_order.php", {
    method: "POST",
    body: JSON.stringify({ amount: 200, customer_id: data.mobile, ...data }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success === false) {
      }
      if (!data.payment_session_id) {
        alert("Failed to create payment session. Please try again.");
        return;
      }
      //2. Open Cashfree checkout modal
      let checkoutOptions = {
        paymentSessionId: data.payment_session_id,
        redirectTarget: "_modal",
      };

      cashfree.checkout(checkoutOptions).then((result) => {
        if (result.error) {
          alert("There is some payment error, Check for Payment Status");
          console.error(result.error);
        }
        if (result.redirect) {
          console.log("Payment will be redirected");
        }
        if (result.paymentDetails) {
          history.pushState(null, null, location.href);
          window.onpopstate = function () {
            history.pushState(null, null, location.href);
          };

          window.location.href =
            "https://elle-noisy-carelessly.ngrok-free.dev/stremax-hiring/assets/cashfree/verify.html?txnId=" +
            data.order_id;
        }
      });
    })
    .catch((err) => {
      console.error("Error during order creation:", err);
      alert("Something went wrong. Please try again.");
    });
}

function loadState() {
  const stateDropdown = document.getElementById("state");
  const districtDropdown = document.getElementById("district");
  // 1. Load Districts
  fetch("assets/get_state.php")
    .then((res) => res.json())
    .then((data) => {
      stateDropdown.innerHTML =
        '<option value="">-- Select a State --</option>';
      data.forEach((state) => {
        const option = new Option(state.sname, state.st_id);
        stateDropdown.add(option);
      });
    });

  // 2. State Change Logic
  stateDropdown.addEventListener("change", function () {
    const selectedStateId = this.value;

    // Check if position is BCO before fetching
    if (selectedStateId) {
      districtDropdown.disabled = false;
      fetchDistrict(selectedStateId, "district");
    } else {
      clearDistrictDropdown(districtId);
    }
  });
}

// Clear all district dropdowns
function clearDistrictDropdown(districtId) {
  const districtDropdown = document.getElementById(`${districtId}`);
  districtDropdown.innerHTML =
    '<option value="">-- Select a District --</option>';
  districtDropdown.disabled = true;
}

// Clear all block dropdowns
function clearBlockDropdown(blockId) {
  const blockDropdown = document.getElementById(`${blockId}`);
  blockDropdown.innerHTML = '<option value="">-- Select a Block --</option>';
  blockDropdown.disabled = true;
}

// Loading Districts from db
function fetchDistrict(st_id, districtId = "district") {
  const districtDropdown = document.getElementById(`${districtId}`);
  const positionDropdown = document.getElementById("position");
  const blockDropdown = document.getElementById("block");
  const exclude = positionDropdown.value === "DCO";

  // Show loading state in all district dropdowns
  districtDropdown.innerHTML = "<option>-- Loading Districts --</option>";
  districtDropdown.disabled = true;

  // Fetch district based on selected state
  fetch(`assets/get_district.php?st_id=${st_id}&exclude=${exclude}`)
    .then((response) => response.json())
    .then((data) => {
      // Update all district dropdowns
      districtDropdown.innerHTML =
        '<option value="">-- Select a District --</option>';
      data.forEach((district) => {
        const option = new Option(district.dname, district.d_id);
        districtDropdown.add(option);
      });
      districtDropdown.disabled = false;

      // 2. District Change Logic
      districtDropdown.addEventListener("change", function () {
        const selectedDistrictId = this.value;

        // Check if position is BCO before fetching
        if (selectedDistrictId && positionDropdown.value === "BCO") {
          blockDropdown.disabled = false;
          fetchBlocks(selectedDistrictId, "block");
        } else {
          blockDropdown.disabled = true;
          blockDropdown.innerHTML = '<option value="">Not Required</option>';
        }
      });

      // 3. Position Change Logic (In case they change Position after District)
      positionDropdown.addEventListener("change", function () {
        if (this.value === "BCO" && districtDropdown.value !== "") {
          blockDropdown.disabled = false;
          fetchBlocks(districtDropdown.value, "block");
        } else {
          blockDropdown.disabled = true;
          blockDropdown.innerHTML = '<option value="">Not Required</option>';
        }
      });
    });
}

// Loading blocks from database
function fetchBlocks(d_id, blockId = "block") {
  const blockDropdown = document.getElementById(`${blockId}`);

  // Show loading state in all block dropdowns
  blockDropdown.innerHTML = "<option>-- Loading Blocks --</option>";
  blockDropdown.disabled = true;

  // Fetch block based on selected district
  fetch(`assets/get_block.php?d_id=${d_id}`)
    .then((response) => response.json())
    .then((data) => {
      // Update all block dropdowns
      blockDropdown.innerHTML =
        '<option value="">-- Select a Block --</option>';

      if (data.length > 0) {
        data.forEach((block) => {
          const option = document.createElement("option");
          option.value = block.b_id;
          option.textContent = block.bname;
          option.dataset.b_name = block.bname;
          blockDropdown.appendChild(option);
        });
        blockDropdown.disabled = false;
      } else {
        blockDropdown.innerHTML =
          '<option value="">-- No Blocks Available --</option>';
      }
    })
    .catch((error) => {
      console.error("Error fetching block:", error);
      blockDropdown.innerHTML =
        '<option value="">-- Error Loading Blocks --</option>';
    });
}
