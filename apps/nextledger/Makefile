APP_NAME := $(or $(APP_NAME),$(shell sed -n 's:.*<id>\\(.*\\)</id>.*:\\1:p' appinfo/info.xml | head -n1))
CERT_DIR := $(HOME)/.nextcloud/certificates
PRIVATE_KEY := $(CERT_DIR)/$(APP_NAME).key
PUBLIC_CRT := $(CERT_DIR)/$(APP_NAME).crt
OCC := ../../occ
BUILD_DIR := build
APPSTORE_DIR := $(BUILD_DIR)/artifacts/appstore/$(APP_NAME)
ARCHIVE := $(BUILD_DIR)/artifacts/appstore/$(APP_NAME).tar.gz

.PHONY: appstore clean

appstore:
	@if [ -z "$(APP_NAME)" ]; then echo "APP_NAME is empty (check appinfo/info.xml or pass APP_NAME=...)"; exit 1; fi
	@test -f "$(PRIVATE_KEY)" || (echo "Missing private key: $(PRIVATE_KEY)" && exit 1)
	@test -f "$(PUBLIC_CRT)" || (echo "Missing certificate: $(PUBLIC_CRT)" && exit 1)
	@test -f "$(OCC)" || (echo "Missing occ at $(OCC) (run inside Nextcloud server)" && exit 1)
	php "$(OCC)" integrity:sign-app --path "$(CURDIR)" --privateKey "$(PRIVATE_KEY)" --certificate "$(PUBLIC_CRT)"
	mkdir -p "$(APPSTORE_DIR)"
	rsync -a --delete \
		--exclude ".git" \
		--exclude ".github" \
		--exclude "node_modules" \
		--exclude "tests" \
		--exclude "docs" \
		--exclude "$(BUILD_DIR)" \
		--exclude ".DS_Store" \
		./ "$(APPSTORE_DIR)/"
	mkdir -p "$(dir $(ARCHIVE))"
	tar -czf "$(ARCHIVE)" -C "$(BUILD_DIR)/artifacts/appstore" "$(APP_NAME)"
	@echo "Created $(ARCHIVE)"

clean:
	rm -rf "$(BUILD_DIR)"
