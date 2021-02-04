/**
 * g++ -O3 -march=native -mavx test_accumulate_inline.cpp -o native_accumulate_inline_opt
 * g++ -O0 -march=native test_accumulate_inline.cpp -o native_accumulate_inline_no_opt
 */

#include <cstdio>

double accumulate(const long iMax) {
    double fAcum = 0.0;
    for (long i = 1; i<iMax; i++) {
        fAcum += 0.001 * i;
    }
    return fAcum;
}


int main(void) {
    double fAcum = accumulate(2000000000);
    std::printf("%g\n", fAcum);
    return 0;
}
