MAKEFILE_PATH := $(dir $(lastword $(MAKEFILE_LIST)))
NODE_PATH := $(MAKEFILE_PATH)test,$(MAKEFILE_PATH):$(NODE_PATH)
REPORTER ?= spec
MOCHA ?= ./node_modules/.bin/mocha
BROWSERIFY = ./node_modules/.bin/browserify
UGLIFYJS = ./node_modules/.bin/uglifyjs
FLAGS = -t uglifyify


all: community-translator.js community-translator.min.js community-translator.css

community-translator.js: browserify

community-translator.min.js: uglifyjs

browserify:
	$(BROWSERIFY) $(FLAGS) lib/index.js --standalone communityTranslator -o community-translator.js

uglifyjs:
	$(UGLIFYJS) community-translator.js -c > community-translator.min.js

community-translator.css: css/custom.css
	cat css/jquery.webui*.css css/custom.css > community-translator.css

test:
	@NODE_PATH=$(NODE_PATH) $(MOCHA) --reporter $(REPORTER) test/index.js

.PHONY: test
