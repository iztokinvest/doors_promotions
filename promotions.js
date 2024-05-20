document.addEventListener("DOMContentLoaded", function () {
	const promoShortcode = document.getElementById("promo_shortcode");
	const promoCategories = document.getElementById("promo_categories");
	const promoImageUploadInput = document.getElementById("promo_image");
	const promoImageUploadPreview = document.getElementById("promo_image_preview");
	const uploadedImages = document.querySelectorAll(".uploadedImages");
	const datepickers = document.querySelectorAll(".datepicker-input");

	(function () {
		Datepicker.locales.bg = {
			days: ["Неделя", "Понеделник", "Вторник", "Сряда", "Четвъртък", "Петък", "Събота"],
			daysShort: ["Нед", "Пон", "Вто", "Сря", "Чет", "Пет", "Съб"],
			daysMin: ["Нд", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"],
			months: [
				"Януари",
				"Февруари",
				"Март",
				"Април",
				"Май",
				"Юни",
				"Юли",
				"Август",
				"Септември",
				"Октомври",
				"Ноември",
				"Декември",
			],
			monthsShort: ["Ян", "Фев", "Мар", "Апр", "Май", "Юни", "Юли", "Авг", "Сеп", "Окт", "Ное", "Дек"],
		};
	})();

	datepickers.forEach((element) => {
		const datepicker = new Datepicker(element, {
			format: "dd/mm/yyyy",
			daysOfWeekHighlighted: [6,0],
			autohide: true,
			weekStart: 1,
			language: "bg",
		});
	});

	if (promoShortcode) {
		promoShortcode.addEventListener("change", (event) => {
			const trigger = event.currentTarget;

			if (trigger.value == "product_shortcode") {
				promoCategories.style.display = "table";
			} else {
				promoCategories.style.display = "none";
			}
		});
	}

	if (promoImageUploadInput) {
		promoImageUploadInput.addEventListener("change", function (event) {
			const file = event.target.files[0];
			if (file) {
				const reader = new FileReader();
				reader.onload = function (e) {
					promoImageUploadPreview.src = e.target.result;
					promoImageUploadPreview.style.display = "block";
				};
				reader.readAsDataURL(file);
			}
		});
	}

	if (uploadedImages.length > 0) {
		uploadedImages.forEach((image) => {
			const filename = image.src.split("/").pop().split(".").pop();
			const width = image.naturalWidth;
			const height = image.naturalHeight;

			const infoText = `${filename} - ${width}x${height}`;
			const textNode = document.createTextNode(infoText);

			// Create a new element to hold the text information
			const infoDiv = document.createElement("div");
			infoDiv.appendChild(textNode);

			// Insert the new div after the image
			image.closest("td").insertBefore(infoDiv, image.nextSibling);
		});
	}
	
	document.querySelectorAll('.base-category').forEach(function(baseCheckbox) {
        baseCheckbox.addEventListener('change', function() {
            var categoryId = this.getAttribute('data-category-id');
            var subCategories = document.querySelectorAll('.sub-category[data-parent-id="' + categoryId + '"]');
            subCategories.forEach(function(subCheckbox) {
                subCheckbox.disabled = baseCheckbox.checked;
                if (baseCheckbox.checked) {
                    subCheckbox.checked = false; // Uncheck subcategory if base is checked
                }
            });
        });
    });
});
