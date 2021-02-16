# php8-playground

## About

A place for messing about with PHP8 to better understand its theoretical and actual performance.

## PHP Version
```
PHP 8.0.1 (cli) (built: Jan 13 2021 08:21:37) ( NTS )
Copyright (c) The PHP Group
Zend Engine v4.0.1, Copyright (c) Zend Technologies
    with Zend OPcache v8.0.1, Copyright (c), by Zend Technologies

```

## JIT Settings

CLI Opcache enabled in default configuration. JIT specific parameters passed as CLI parameters to PHP for test flexibility. Unless otherwise stated, all tests conducted with the following settings:

```
opcache.jit_buffer_size=1M
opcache.jit=1255
```

Notes:

The JIT flags is actually a numeric string comprising of the following four individual properties:

- C (CPU specific optimisations):
    - 0 None.
    - 1 Enable AVX instruction generation.
- R (Register allocator):
    - 0 No register allocation.
    - 1 Local liner-scan register allocation.
    - 2 Global liner-scan register allocation.
- T (JIT Trigger):
    - 0 JIT all functions on script load.
    - 1 JIT function on first execution.
    - 2 Profile on first request and profile hot functions on second request.
    - 3 Profile on the fly and compile hot functions.
    - 4 Compile functions with @jit tag in doc comments.
    - 5 Tracing JIT.
- O (Optimisation Level):
    - 0 No JIT.
    - 1 Minimal JIT (call standard VM handlers).
    - 2 Selective VM handler inlining.
    - 3 Optimised JIT based on static type inference of individual function.
    - 4 Optimised JIT based on static type inference and call tree.
    - 5 Optimised JIT based on static type inference and inner procedure analyeses.

Given the higher levels of optimisation perform type inference, we can infer that code that is strongly typed ought to perform better and that implicit type conversion is likely to hurt.

## Test Hardware

All tests conducted in a single threaded model.

```
processor       : 0
vendor_id       : GenuineIntel
cpu family      : 6
model           : 142
model name      : Intel(R) Core(TM) i7-7500U CPU @ 2.70GHz
stepping        : 9
microcode       : 0xde
cpu MHz         : 2194.106
cache size      : 4096 KB
physical id     : 0
siblings        : 4
core id         : 0
cpu cores       : 2
apicid          : 0
initial apicid  : 0
fpu             : yes
fpu_exception   : yes
cpuid level     : 22
wp              : yes
flags           : fpu vme de pse tsc msr pae mce cx8 apic sep mtrr pge mca cmov pat pse36 clflush dts acpi mmx fxsr sse sse2 ss ht tm pbe syscall nx pdpe1gb rdtscp lm constant_tsc art arch_perfmon pebs bts rep_good nopl xtopology nonstop_tsc cpuid aperfmperf pni pclmulqdq dtes64 monitor ds_cpl vmx est tm2 ssse3 sdbg fma cx16 xtpr pdcm pcid sse4_1 sse4_2 x2apic movbe popcnt tsc_deadline_timer aes xsave avx f16c rdrand lahf_lm abm 3dnowprefetch cpuid_fault epb invpcid_single pti ssbd ibrs ibpb stibp tpr_shadow vnmi flexpriority ept vpid ept_ad fsgsbase tsc_adjust bmi1 avx2 smep bmi2 erms invpcid mpx rdseed adx smap clflushopt intel_pt xsaveopt xsavec xgetbv1 xsaves dtherm ida arat pln pts hwp hwp_notify hwp_act_window hwp_epp md_clear flush_l1d
bugs            : cpu_meltdown spectre_v1 spectre_v2 spec_store_bypass l1tf mds swapgs itlb_multihit srbds
bogomips        : 5799.77
clflush size    : 64
cache_alignment : 64
address sizes   : 39 bits physical, 48 bits virtual
power management:
```

## Function Call Overhead Test

Function call overhead for a small (simple multiply-accumulate) function is tested against the same operation performed inline.

- All times are User time, in seconds, collected by the `time` command.
- Strict indicates whether the code was excuted with `declare(strict_types=1)` or not.
- The Increase with JIT is the speedup factor with JIT enabled, i.e. how many times faster the code is with JIT enabled versus disabled.
- Call Overhead is the slowdown factor for calling the function relative to the inline version, i.e. how many times slower the code is versus the inline operation.

| Operation | Strict? | JIT Disabled | JIT Enabled | Increase with JIT | Call Overhead | Call Overhead (JIT) |
| --------- | ------- | ------------ | ----------- | - | - | - |
| inline    | No      | 11.87        | 2.94        | 4.04 | _N/A_ | _N/A_ |
| inline    | Yes     | 11.80        | 2.90        | 4.07 | _N/A_ | _N/A_ |
| direct    | No      | 34.26        | 12.91       | 2.65 | 2.89 | 4.39 |
| direct    | Yes     | 34.28        | 12.82       | 2.67 | 2.91 | 4.42 |
| indirect  | No      | 42.09        | 13.91       | 3.03 | 3.55 | 4.73 |
| indirect  | Yes     | 42.19        | 13.88       | 3.04 | 3.58 | 4.79 |
| method    | No      | 46.73        | 15.96       | 2.93 | 3.94 | 5.43 |
| method    | Yes     | 46.89        | 15.99       | 2.93 | 3.97 | 5.51 |
| closure   | No      | 62.39        | 16.74       | 3.73 | 5.26 | 5.69 |
| closure   | Yes     | 62.32        | 16.73       | 3.73 | 5.28 | 5.77 |
| magic 1   | No      | 136.86       | 129.77      | 1.05 | 11.60 | 44.75 |
| magic 1   | Yes     | 140.64       | 122.02      | 1.15 | 11.92 | 42.08 |
| magic 2   | No      | 151.53       | 131.52      | 1.15 | 12.77 | 44.74 |
| magic 2   | Yes     | 155.48       | 131.99      | 1.18 | 13.18 | 45.51 |


Notes:

- direct is a call do a globally defined function.
- indirect is a call by function name to a globally defined function.
- method is a call to a member function on an instance of a class.
- closure is a call to a locally declared anonymous function.
- magic 1 is via __call() hook
- magic 2 is via __callStatic() hook

### Theoretical Best

Compare the above results with compiled C++ equivalent versions on the same hardware:

| Operation | No Optimisation | Max Optimisation | Increase with Optimisation | Call Overhead (No Optimisation) |
| --------- | --------------- | ---------------- | ---------------------------| - |
| inline    | 6.88            | 2.30             | 2.99 | _N/A_ |
| direct    | 6.96            | 2.30             | 3.03 | 1.012 |

Notes:

- Used gcc 9.3.0
- No Optimisation is -O0 -march-native
- Max Optimisation is -O3 -march-native -mavx
- Function call overhead was extremely small here:
    - Included for No Optimisation case as the function is always inlined in the Max Optimisation case.
    - Generation of non-inlined direct function call confirmed in assembler output.
    - Had to perform multiple runs to extract the actual timing difference from the variance between runs.

### Conclusion

- Strict type enforcement has no significant impact on either execution model.
- JIT mode can offer significant speed up for simple imperative code.
    - Up to 78.2% of the optimised native version for the _inline_ example!
- Function call overhead remains large in either execution model but has a larger impact on JIT executed code.
    - Markedly different from native code where the impact of inlining in the example above was difficult to measure.


### JIT Analysis

- [Inline Example](./docs/jit_inline_analysis.md)
- [Direct Call Example](./docs/jit_direct_call_analysis.md)
