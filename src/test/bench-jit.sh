#!/bin/sh

time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_inline_nonstrict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_inline_strict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_call_direct_nonstrict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_call_direct_strict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_call_indirect_nonstrict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_call_indirect_strict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_method_call_direct_nonstrict.php
time -v php -dopcache.jit_buffer_size=1M -dopcache.jit=1255 test_accumulate_method_call_direct_strict.php
