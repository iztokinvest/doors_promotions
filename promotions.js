document.addEventListener("DOMContentLoaded", function () {
	const promoShortcode = document.getElementById("promo_shortcode");
	const promoCategories = document.getElementById("promo_categories");
	const promoImageUploadInput = document.getElementById("promo_image");
	const promoImageUploadPreview = document.getElementById("promo_image_preview");
	const uploadedImages = document.querySelectorAll(".uploadedImages");
	const datepickers = document.querySelectorAll(".datepicker-input");
	const removeFileUpload = document.getElementById("remove-file-upload");
	const promotionsListTable = document.getElementById("promotions-list-table");

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
			daysOfWeekHighlighted: [6, 0],
			autohide: true,
			weekStart: 1,
			language: "bg",
		});
	});

	if (promoShortcode) {
		promoShortcode.addEventListener("change", (event) => {
			const trigger = event.currentTarget;

			if (trigger.value.includes("product")) {
				promoCategories.style.display = "table-row";
			} else {
				promoCategories.style.display = "none";
				const checkboxes = document.getElementsByName("promo_categories[]");
				checkboxes.forEach((el) => el.removeAttribute("checked"));
			}
		});
	}

	if (promoImageUploadInput) {
		promoImageUploadInput.addEventListener("change", function (event) {
			const file = event.target.files[0];
			if (file) {
				if (file.type === "image/jpeg" || file.type === "image/png") {
					const reader = new FileReader();
					reader.onload = function (e) {
						promoImageUploadPreview.src = e.target.result;
						promoImageUploadPreview.style.display = "block";
					};
					reader.readAsDataURL(file);
				} else {
					notifier.warning("Разрешени са само файлове с формат JPG и PNG");
					promoImageUploadInput.value = null;
					promoImageUploadPreview.style.display = "none";
				}
			}
		});
	}

	if (uploadedImages.length > 0) {
		uploadedImages.forEach((image) => {
			const parts = image.src.split("/");
			const filenameWithExtension = parts[parts.length - 1];
			const filenameParts = filenameWithExtension.split(".");
			const extension = filenameParts[filenameParts.length - 1];
			const width = image.naturalWidth;
			const height = image.naturalHeight;

			const infoText = `${extension} - ${width}x${height}`;
			const textNode = document.createTextNode(infoText);

			// Create a new element to hold the text information
			const infoDiv = document.createElement("div");
			infoDiv.classList.add("image-info");
			infoDiv.setAttribute("data-filename", filenameWithExtension);
			infoDiv.appendChild(textNode);

			image.closest("td").insertBefore(infoDiv, image.nextSibling);
		});
	}

	document.querySelectorAll(".base-category").forEach(function (baseCheckbox) {
		baseCheckbox.addEventListener("change", function () {
			var categoryId = this.getAttribute("data-category-id");
			var subCategories = document.querySelectorAll('.sub-category[data-parent-id="' + categoryId + '"]');
			subCategories.forEach(function (subCheckbox) {
				subCheckbox.disabled = baseCheckbox.checked;
				if (baseCheckbox.checked) {
					subCheckbox.checked = false; // Uncheck subcategory if base is checked
				}
			});
		});
	});

	document.querySelectorAll("textarea.template_content").forEach(function (textarea) {
		const editor = CodeMirror.fromTextArea(textarea, {
			lineNumbers: true,
			mode: "htmlmixed",
			theme: "monokai",
			lineWrapping: true,
			gutters: ["CodeMirror-lint-markers"],
			lint: true,
		});

		function checkForErrors() {
			const currentTr = textarea.closest("tr");
			const templateButton = currentTr.querySelector(".template-button");
			const lintErrors = editor.state.lint.marked.length > 0;
			if (!lintErrors) {
				templateButton.style.display = "block";
			} else {
				templateButton.style.display = "none";
			}
		}

		editor.on("update", checkForErrors);
		editor.on("change", checkForErrors);
	});

	if (removeFileUpload) {
		removeFileUpload.addEventListener("click", function () {
			const isChecked = removeFileUpload.checked;
			promoImageUploadInput.style.display = isChecked ? "none" : "";
			promoImageUploadInput.required = !isChecked;
			promoImageUploadInput.value = "";
			promoImageUploadPreview.style.display = "none";
		});
	}

	if (promotionsListTable) {
		promotionsListTable.addEventListener("click", function (event) {
			const target = event.target;

			if (target.classList.contains("image-info")) {
				const filename = target.getAttribute("data-filename");

				navigator.clipboard
					.writeText(filename)
					.then(() => {
						if (notifier.clear) {
							notifier.clear();
						}

						notifier.success(`Името файла ${filename} е копирано в клипборда.`);
					})
					.catch((err) => {
						notifier.alert(`Грешка при копиране на името на файла: ${err}`);
					});
			}
		});
	}
});

const notifier = new AWN({
	durations: {
		global: 5000,
		position: "bottom-right",
	},
});

hash = window.location.hash;

if (hash) {
	hash = hash.substring(1);
	if (hash.startsWith("msg=")) {
		hash = hash.substring(4);
		let decodedHash = decodeURIComponent(hash);
		notifier.success(decodedHash);
		history.replaceState("", document.title, window.location.pathname + window.location.search);
	}
}

async function fetchGitHubPromoRelease() {
	const urlParams = new URLSearchParams(window.location.search);
	const hasPromotionsPage = urlParams.get("page") === "promotions";

	if (!hasPromotionsPage) {
		return;
	}

	const response = await fetch("https://api.github.com/repos/iztokinvest/doors_promotions/releases/latest");
	const currentVersion = document.getElementById("promo-extension-version");
	const wpBody = document.getElementById("wpbody-content");

	const data = await response.json();

	if (data.tag_name && currentVersion && data.tag_name != currentVersion.innerHTML) {
		wpBody.insertAdjacentHTML(
			"afterbegin",
			`<div class="alert alert-warning alert-dismissible fade show" role="alert">
				Налична е нова версия на разширението: <strong>${data.tag_name}</strong>. В момента използвате <strong>${currentVersion.innerHTML}</strong>. <a href="?update_promotions=1">Обновете от тук!</a>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>`
		);
	}
}
fetchGitHubPromoRelease();

//AJAX
jQuery(document).ready(function ($) {
	$(".edit-promo").click(function () {
		const $this = $(this);
		const startDate = $this.closest("tr").find(".promo-start-date").val();
		const endDate = $this.closest("tr").find(".promo-end-date").val();

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "edit_promo",
				promo_id: $this.data("id"),
				promo_title: $this.closest("tr").find(".promo-title").val(),
				promo_shortcode: $this.closest("tr").find(".promo-shortcode").val(),
				promo_start_date: startDate,
				promo_end_date: endDate,
				promo_active: $this.closest("tr").find(".promo-active").prop("checked"),
			},
			success: (response) => {
				if (response.success) {
					notifier.success("Банерът е редактиран.");
				}

				function parseDate(dateStr) {
					var parts = dateStr.split("/");
					var day = parseInt(parts[0], 10);
					var month = parseInt(parts[1], 10) - 1;
					var year = parseInt(parts[2], 10);

					year += year < 100 ? 2000 : 0;

					return new Date(year, month, day);
				}

				switch (true) {
					case new parseDate(startDate) > new Date():
						$this.closest("tr").css("background-color", "#00dd7761");
						break;
					case parseDate(endDate) < new Date():
						$this.closest("tr").css("background-color", "#ff000040");
						break;
					case new parseDate(endDate) < new Date(new Date().setDate(new Date().getDate() + 6)):
						$this.closest("tr").css("background-color", "#ffff0040");
						break;
					default:
						$this.closest("tr").css("background-color", "");
						break;
				}
			},
			error: function () {
				frame_notifier.alert(`Грешка при редактиране на банер.`);
			},
		});
	});

	$(".delete-promo").click(function () {
		const $this = $(this);
		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: {
				action: "delete_promo",
				promo_id: $this.data("id"),
			},
			success: (response) => {
				if (response.success) {
					$this.closest("tr").remove();
					notifier.success("Банерът е изтрит.");
				}
			},
			error: function () {
				frame_notifier.alert(`Грешка при изтриване на банер.`);
			},
		});
	});
});
