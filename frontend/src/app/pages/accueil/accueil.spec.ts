import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AccueilComponent } from './accueil';
import { provideRouter } from '@angular/router';

describe('AccueilComponent', () => {
  let component: AccueilComponent;
  let fixture: ComponentFixture<AccueilComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AccueilComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(AccueilComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
